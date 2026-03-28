<?php

namespace VISU\Build;

class PlatformPackager
{
    private BuildConfig $config;

    public function __construct(BuildConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Package the combined binary for the target platform
     *
     * @return string Path to the output directory/bundle
     */
    public function package(string $binaryPath, string $outputDir, string $platform, string $variant = 'base'): string
    {
        return match ($platform) {
            'macos' => $this->packageMacOS($binaryPath, $outputDir, $variant),
            'windows' => $this->packageFlat($binaryPath, $outputDir, '.exe', 'windows', $variant),
            'linux' => $this->packageFlat($binaryPath, $outputDir, '', 'linux', $variant),
            default => throw new \RuntimeException("Unsupported platform: {$platform}"),
        };
    }

    private function packageMacOS(string $binaryPath, string $outputDir, string $variant = 'base'): string
    {
        $name = $this->config->name;
        $appDir = $outputDir . "/{$name}.app";
        $contentsDir = $appDir . '/Contents';
        $macosDir = $contentsDir . '/MacOS';
        $resourcesDir = $contentsDir . '/Resources';

        // Create bundle structure
        foreach ([$macosDir, $resourcesDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Copy binary
        copy($binaryPath, $macosDir . '/' . $name);
        chmod($macosDir . '/' . $name, 0755);

        // Copy bundle libs (e.g. libsteam_api.dylib) next to binary
        $this->copyBundleLibs($macosDir, 'macos');

        // Generate Info.plist
        $this->writeInfoPlist($contentsDir);

        // Copy external resources to Resources/
        $this->copyExternalResources($resourcesDir);

        // Copy icon if configured
        $macosConfig = $this->config->platforms['macos'] ?? [];
        if (isset($macosConfig['icon'])) {
            $iconPath = $this->config->projectRoot . '/' . $macosConfig['icon'];
            if (file_exists($iconPath)) {
                copy($iconPath, $resourcesDir . '/' . basename($iconPath));
            }
        }

        return $appDir;
    }

    private function packageFlat(string $binaryPath, string $outputDir, string $extension, string $platform, string $variant = 'base'): string
    {
        $name = $this->config->name;
        $dir = $outputDir . '/' . $name;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $targetName = $name . $extension;
        copy($binaryPath, $dir . '/' . $targetName);
        chmod($dir . '/' . $targetName, 0755);

        // Copy bundle libs next to binary
        $this->copyBundleLibs($dir, $platform);

        // Copy external resources alongside binary
        $this->copyExternalResources($dir);

        return $dir;
    }

    private function writeInfoPlist(string $contentsDir): void
    {
        $name = $this->config->name;
        $identifier = $this->config->identifier;
        $version = $this->config->version;
        $macosConfig = $this->config->platforms['macos'] ?? [];
        $minVersion = $macosConfig['minimumVersion'] ?? '12.0';

        $iconFile = '';
        if (isset($macosConfig['icon'])) {
            $iconFile = basename($macosConfig['icon']);
        }

        $plist = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleName</key>
    <string>{$name}</string>
    <key>CFBundleDisplayName</key>
    <string>{$name}</string>
    <key>CFBundleIdentifier</key>
    <string>{$identifier}</string>
    <key>CFBundleVersion</key>
    <string>{$version}</string>
    <key>CFBundleShortVersionString</key>
    <string>{$version}</string>
    <key>CFBundleExecutable</key>
    <string>{$name}</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleIconFile</key>
    <string>{$iconFile}</string>
    <key>LSMinimumSystemVersion</key>
    <string>{$minVersion}</string>
    <key>NSHighResolutionCapable</key>
    <true/>
    <key>NSSupportsAutomaticGraphicsSwitching</key>
    <true/>
</dict>
</plist>
XML;

        file_put_contents($contentsDir . '/Info.plist', $plist);
    }

    private function copyBundleLibs(string $targetDir, string $platform): void
    {
        $libs = $this->config->bundleLibs[$platform] ?? [];
        foreach ($libs as $lib) {
            $src = $lib['src'];
            $optional = $lib['optional'] ?? false;

            // Resolve relative paths against project root
            if ($src !== '' && $src[0] !== '/') {
                $src = $this->config->projectRoot . '/' . $src;
            }

            if (!file_exists($src)) {
                if ($optional) {
                    continue;
                }
                throw new \RuntimeException("Bundle lib not found: {$src}");
            }
            copy($src, $targetDir . '/' . basename($src));
        }
    }

    private function copyExternalResources(string $targetDir): void
    {
        foreach ($this->config->externalResources as $resourcePath) {
            $src = $this->config->projectRoot . '/' . $resourcePath;
            if (!is_dir($src)) continue;

            $dst = $targetDir . '/' . $resourcePath;
            $this->copyDirectory($src, $dst);
        }
    }

    private function copyDirectory(string $src, string $dst): void
    {
        @mkdir($dst, 0755, true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
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
}
