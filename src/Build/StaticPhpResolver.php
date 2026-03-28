<?php

namespace VISU\Build;

class StaticPhpResolver
{
    private const GITHUB_REPO = 'hmennen90/static-php-cli';

    private string $cacheDir;

    /** @var callable|null */
    private $logger = null;

    public function __construct()
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
        $this->cacheDir = $home . '/.visu/build-cache';
    }

    /**
     * @param callable(string): void $logger
     */
    public function setLogger(callable $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Resolve a micro.sfx binary path.
     * Priority: explicit path > cached > download from GitHub Release
     */
    public function resolve(?string $explicitPath, string $platform, string $arch, string $variant = 'base'): string
    {
        // 1. Explicit path from CLI
        if ($explicitPath !== null) {
            if (!file_exists($explicitPath)) {
                throw new \RuntimeException("micro.sfx not found at: {$explicitPath}");
            }
            return $explicitPath;
        }

        // 2. Check cache (variant-specific, then fallback to base)
        $cacheKey = $variant !== 'base' ? "{$platform}-{$arch}-{$variant}" : "{$platform}-{$arch}";
        $cachedPath = $this->cacheDir . "/{$cacheKey}/micro.sfx";
        if (file_exists($cachedPath)) {
            return $cachedPath;
        }

        // 3. Download from GitHub Release
        $this->log("No cached micro.sfx for {$cacheKey}, checking GitHub releases...");
        $downloaded = $this->downloadFromRelease($platform, $arch, $variant);
        if ($downloaded !== null) {
            return $downloaded;
        }

        throw new \RuntimeException(
            "No micro.sfx binary found for {$variant}/{$platform}-{$arch}.\n\n" .
            "Options:\n" .
            "  1. Provide one with --micro-sfx <path>\n" .
            "  2. Trigger the 'Build Game micro.sfx' workflow in hmennen90/static-php-cli\n" .
            "  3. Cache a pre-built binary:\n" .
            "     mkdir -p ~/.visu/build-cache/{$cacheKey}\n" .
            "     cp /path/to/micro.sfx ~/.visu/build-cache/{$cacheKey}/micro.sfx"
        );
    }

    /**
     * Download micro.sfx from the latest GitHub Release.
     *
     * @param string $variant "base" or "steam"
     */
    private function downloadFromRelease(string $platform, string $arch, string $variant = 'base'): ?string
    {
        // Map platform/arch to static-php-cli naming convention
        $osName = match (true) {
            $platform === 'macos' && $arch === 'arm64'   => 'macos-aarch64',
            $platform === 'macos' && $arch === 'x86_64'  => 'macos-x86_64',
            $platform === 'linux' && $arch === 'arm64'   => 'linux-aarch64',
            $platform === 'linux' && $arch === 'x86_64'  => 'linux-x86_64',
            $platform === 'windows'                       => 'windows-x86_64',
            default                                       => "{$platform}-{$arch}",
        };

        // Find latest release with micro.sfx assets
        $releaseUrl = $this->findLatestRuntimeRelease();
        if ($releaseUrl === null) {
            $this->log("No runtime releases found on GitHub");
            return null;
        }

        // Find matching asset by searching release assets with flexible pattern
        // Assets are named: micro-sfx-{variant}-{phpVersion}-{osName}.zip
        $downloadUrl = null;
        $matchedAsset = null;
        $prefix = "micro-sfx-{$variant}-";
        $suffix = "-{$osName}.zip";

        $json = $this->httpGet($releaseUrl);
        $release = $json !== null ? json_decode($json, true) : null;
        if (is_array($release) && isset($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                $name = $asset['name'] ?? '';
                if (str_starts_with($name, $prefix) && str_ends_with($name, $suffix)) {
                    $downloadUrl = $asset['browser_download_url'] ?? null;
                    $matchedAsset = $name;
                    break;
                }
            }
        }

        if ($downloadUrl === null) {
            $this->log("No matching micro.sfx asset found for {$variant}/{$osName}");
            return null;
        }

        // Download and cache
        $this->log("Downloading {$matchedAsset}...");
        $tempFile = tempnam(sys_get_temp_dir(), 'visu-micro-');
        if ($tempFile === false) {
            return null;
        }

        $content = $this->httpGet($downloadUrl);
        if ($content === null) {
            @unlink($tempFile);
            return null;
        }

        file_put_contents($tempFile, $content);

        // If it's a zip, extract micro.sfx from it
        $actualFile = $tempFile;
        if (str_ends_with($matchedAsset, '.zip') && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) === true) {
                $extractDir = sys_get_temp_dir() . '/visu-micro-extract-' . getmypid();
                $zip->extractTo($extractDir);
                $zip->close();
                // Find micro.sfx inside
                foreach (['micro.sfx', 'micro.sfx.exe'] as $binName) {
                    $candidate = $extractDir . '/' . $binName;
                    if (!file_exists($candidate)) {
                        $candidate = $extractDir . '/buildroot/bin/' . $binName;
                    }
                    if (file_exists($candidate)) {
                        $actualFile = $candidate;
                        break;
                    }
                }
            }
        }

        $cacheKey = $variant !== 'base' ? "{$platform}-{$arch}-{$variant}" : "{$platform}-{$arch}";
        $cachedDir = $this->cacheDir . "/{$cacheKey}";
        if (!is_dir($cachedDir)) {
            mkdir($cachedDir, 0755, true);
        }
        $cachedPath = $cachedDir . '/micro.sfx';
        copy($actualFile, $cachedPath);
        chmod($cachedPath, 0755);

        // Cleanup
        @unlink($tempFile);
        if (isset($extractDir) && is_dir($extractDir)) {
            $this->removeDir($extractDir);
        }

        $size = filesize($cachedPath);
        $this->log(sprintf("Downloaded and cached: %s (%.1f MB)", $cachedPath, $size / 1024 / 1024));

        return $cachedPath;
    }

    /**
     * Find the latest GitHub Release that contains micro.sfx assets.
     */
    private function findLatestRuntimeRelease(): ?string
    {
        $url = "https://api.github.com/repos/" . self::GITHUB_REPO . "/releases";
        $json = $this->httpGet($url);
        if ($json === null) {
            return null;
        }

        $releases = json_decode($json, true);
        if (!is_array($releases)) {
            return null;
        }

        foreach ($releases as $release) {
            if (!isset($release['tag_name'], $release['url'])) {
                continue;
            }
            if (!empty($release['assets'])) {
                foreach ($release['assets'] as $asset) {
                    if (str_contains($asset['name'] ?? '', 'micro')) {
                        return $release['url'];
                    }
                }
            }
        }

        return null;
    }

    private function removeDir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * HTTP GET with proper headers for GitHub API
     */
    private function httpGet(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: VISU-Build/1.0',
                    'Accept: application/vnd.github+json',
                ],
                'timeout' => 30,
                'follow_location' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result !== false ? $result : null;
    }

    /**
     * Cache a micro.sfx binary for future use
     */
    public function cache(string $sourcePath, string $platform, string $arch): string
    {
        $dir = $this->cacheDir . "/{$platform}-{$arch}";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $target = $dir . '/micro.sfx';
        copy($sourcePath, $target);
        chmod($target, 0755);

        return $target;
    }

    /**
     * Detect current platform
     */
    public static function detectPlatform(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            'Windows' => 'windows',
            default => 'linux',
        };
    }

    /**
     * Detect current architecture
     */
    public static function detectArch(): string
    {
        $uname = php_uname('m');
        return match (true) {
            str_contains($uname, 'arm64'), str_contains($uname, 'aarch64') => 'arm64',
            default => 'x86_64',
        };
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }
}
