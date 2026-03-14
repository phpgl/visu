<?php

namespace VISU\Build;

use Phar;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PharBuilder
{
    private BuildConfig $config;

    public function __construct(BuildConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Create staging directory with resolved symlinks and filtered content
     */
    public function stage(string $stagingDir): void
    {
        if (is_dir($stagingDir)) {
            $this->removeDirectory($stagingDir);
        }
        mkdir($stagingDir, 0755, true);

        $projectRoot = $this->config->projectRoot;

        // Stage vendor/ with symlink resolution
        $vendorSrc = $projectRoot . '/vendor';
        $vendorDst = $stagingDir . '/vendor';
        if (is_dir($vendorSrc)) {
            $excludeArgs = '';
            foreach ($this->config->pharExclude as $pattern) {
                // Convert glob patterns to rsync excludes
                $exclude = str_replace('**/', '', $pattern);
                $excludeArgs .= ' --exclude=' . escapeshellarg($exclude);
            }

            $cmd = sprintf(
                'rsync -aL --delete %s %s %s',
                escapeshellarg($vendorSrc . '/'),
                escapeshellarg($vendorDst . '/'),
                $excludeArgs
            );
            exec($cmd, $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \RuntimeException("Failed to stage vendor directory: " . implode("\n", $output));
            }
        }

        // Stage src/
        $srcDir = $projectRoot . '/src';
        if (is_dir($srcDir)) {
            $this->copyDirectory($srcDir, $stagingDir . '/src');
        }

        // Stage root PHP files and config
        $rootFiles = ['bootstrap.php', 'bootstrap_constants.php', 'app.ctn', 'game.php'];
        foreach ($rootFiles as $file) {
            $path = $projectRoot . '/' . $file;
            if (file_exists($path)) {
                copy($path, $stagingDir . '/' . $file);
            }
        }

        // Stage entry file if different from defaults
        $entry = $this->config->entry;
        if (!in_array($entry, $rootFiles) && file_exists($projectRoot . '/' . $entry)) {
            copy($projectRoot . '/' . $entry, $stagingDir . '/' . $entry);
        }

        // Stage small resource directories (exclude external ones like audio)
        $resourcesDir = $projectRoot . '/resources';
        if (is_dir($resourcesDir)) {
            $external = array_map(function ($path) {
                return basename($path);
            }, $this->config->externalResources);

            $entries = scandir($resourcesDir);
            if ($entries !== false) {
                foreach ($entries as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $itemPath = $resourcesDir . '/' . $item;
                    if (is_dir($itemPath) && !in_array($item, $external) && !in_array('resources/' . $item, $this->config->externalResources)) {
                        $this->copyDirectory($itemPath, $stagingDir . '/resources/' . $item);
                    } elseif (is_file($itemPath)) {
                        @mkdir($stagingDir . '/resources', 0755, true);
                        copy($itemPath, $stagingDir . '/resources/' . $item);
                    }
                }
            }
        }
    }

    /**
     * Create PHAR from staged directory
     */
    public function build(string $stagingDir, string $pharPath): void
    {
        if (file_exists($pharPath)) {
            unlink($pharPath);
        }

        $phar = new Phar($pharPath, 0, basename($pharPath));
        $phar->startBuffering();
        $phar->buildFromDirectory($stagingDir);
        $phar->setStub($this->generateStub());
        $phar->stopBuffering();
    }

    /**
     * Generate the PHAR stub programmatically.
     * Handles micro SAPI, macOS .app bundles, VISU path constants,
     * framework resource extraction, and additional requires.
     */
    public function generateStub(): string
    {
        $additionalRequires = '';
        foreach ($this->config->additionalRequires as $require) {
            $additionalRequires .= "\nrequire_once \$pharBase . '/{$require}';";
        }

        $runCode = '';
        if ($this->config->run !== '') {
            $runCode = "\n" . $this->config->run;
        }

        return <<<'STUB_START'
<?php
// In micro SAPI, PHP_BINARY is empty but __FILE__ points to the binary
$binaryPath = PHP_BINARY ?: __FILE__;
$binaryDir = dirname($binaryPath);
if (str_contains($binaryDir, '.app/Contents/MacOS')) {
    $resourceBase = dirname($binaryDir) . '/Resources';
} else {
    $resourceBase = $binaryDir;
}

$pharBase = 'phar://' . __FILE__;

// Engine log — capture all errors and exceptions to file
$engineLogPath = $resourceBase . '/engine.log';
file_put_contents($engineLogPath, '[' . date('Y-m-d H:i:s') . "] Engine starting...\n");
$__engineLog = function(string $msg) use ($engineLogPath) {
    file_put_contents($engineLogPath, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
};
set_error_handler(function($severity, $message, $file, $line) use ($__engineLog) {
    $type = match($severity) {
        E_WARNING, E_USER_WARNING => 'WARNING',
        E_NOTICE, E_USER_NOTICE => 'NOTICE',
        E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
        default => 'ERROR',
    };
    $__engineLog("{$type}: {$message} in {$file}:{$line}");
    return false; // let PHP handle it too
});
set_exception_handler(function(\Throwable $e) use ($__engineLog) {
    $__engineLog("FATAL: Uncaught " . get_class($e) . ": " . $e->getMessage());
    $__engineLog("  in " . $e->getFile() . ":" . $e->getLine());
    $__engineLog("  Stack trace:\n" . $e->getTraceAsString());
});
register_shutdown_function(function() use ($__engineLog, $engineLogPath) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $__engineLog("FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}");
    }
    $__engineLog("Engine shutdown.");
});

define('DS', DIRECTORY_SEPARATOR);
define('VISU_PATH_ROOT', $resourceBase);
define('VISU_PATH_CACHE', $resourceBase . DS . 'var' . DS . 'cache');
define('VISU_PATH_STORE', $resourceBase . DS . 'var' . DS . 'store');
define('VISU_PATH_RESOURCES', $resourceBase . DS . 'resources');
define('VISU_PATH_APPCONFIG', $resourceBase);
// Vendor lives inside the PHAR
define('VISU_PATH_VENDOR', $pharBase . '/vendor');
// Framework resources (fonts, shaders) need real filesystem access
define('VISU_PATH_FRAMEWORK_RESOURCES', $resourceBase . DS . 'visu-resources');
define('VISU_PATH_FRAMEWORK_RESOURCES_SHADER', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'shader');
define('VISU_PATH_FRAMEWORK_RESOURCES_FONT', VISU_PATH_FRAMEWORK_RESOURCES . DS . 'fonts');

$__engineLog("Resource base: " . $resourceBase);
$__engineLog("PHAR base: " . $pharBase);

@mkdir(VISU_PATH_CACHE, 0755, true);
@mkdir(VISU_PATH_STORE, 0755, true);
@mkdir(VISU_PATH_RESOURCES, 0755, true);

// Extract visu framework resources (fonts, shaders) on first run
if (!is_dir(VISU_PATH_FRAMEWORK_RESOURCES)) {
    $__engineLog("Extracting VISU framework resources...");
    $pharVisuRes = $pharBase . '/vendor/phpgl/visu/resources';
    @mkdir(VISU_PATH_FRAMEWORK_RESOURCES, 0755, true);
    foreach (['fonts', 'shader'] as $subdir) {
        $src = $pharVisuRes . '/' . $subdir;
        $dstBase = VISU_PATH_FRAMEWORK_RESOURCES . DS . $subdir;
        if (is_dir($src)) {
            @mkdir($dstBase, 0755, true);
            $srcLen = strlen($src);
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile()) continue;
                $rel = substr($file->getPathname(), $srcLen + 1);
                $target = $dstBase . DS . $rel;
                @mkdir(dirname($target), 0755, true);
                copy($file->getPathname(), $target);
            }
        }
    }
    $__engineLog("Framework resources extracted.");
}

// Extract game resources (locales, shaders, etc.) from PHAR on first run
$pharResources = $pharBase . '/resources';
if (is_dir($pharResources)) {
    $resIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pharResources, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $pharResLen = strlen($pharResources);
    foreach ($resIterator as $resItem) {
        $relPath = substr($resItem->getPathname(), $pharResLen + 1);
        $targetPath = VISU_PATH_RESOURCES . DS . $relPath;
        if ($resItem->isDir()) {
            @mkdir($targetPath, 0755, true);
        } elseif (!file_exists($targetPath)) {
            @mkdir(dirname($targetPath), 0755, true);
            copy($resItem->getPathname(), $targetPath);
        }
    }
}

// Extract app.ctn if not present
$appCtn = $resourceBase . '/app.ctn';
if (!file_exists($appCtn)) {
    $pharAppCtn = $pharBase . '/app.ctn';
    if (file_exists($pharAppCtn)) {
        file_put_contents($appCtn, file_get_contents($pharAppCtn));
        $__engineLog("Extracted app.ctn");
    }
}

// Ensure container_map.php exists in vendor (write to resource base)
$containerMapFile = $resourceBase . DS . 'vendor' . DS . 'container_map.php';
if (!file_exists($containerMapFile)) {
    @mkdir(dirname($containerMapFile), 0755, true);
    file_put_contents($containerMapFile, "<?php\n\$vendorDir = __DIR__ . '/';\n\nreturn array();\n");
}

$__engineLog("Loading autoloader...");
require $pharBase . '/vendor/autoload.php';
$__engineLog("Bootstrapping VISU...");
$container = require $pharBase . '/vendor/phpgl/visu/bootstrap.php';
$__engineLog("Bootstrap complete.");

STUB_START
        . $additionalRequires
        . "\n\$__engineLog('Running game...');"
        . $runCode . <<<'STUB_END'

__HALT_COMPILER();
STUB_END;
    }

    private function copyDirectory(string $src, string $dst): void
    {
        @mkdir($dst, 0755, true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dst . '/' . substr($item->getPathname(), strlen($src) + 1);
            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @mkdir(dirname($target), 0755, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
