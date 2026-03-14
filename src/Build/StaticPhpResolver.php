<?php

namespace VISU\Build;

class StaticPhpResolver
{
    private const GITHUB_REPO = 'phpgl/visu';

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
    public function resolve(?string $explicitPath, string $platform, string $arch): string
    {
        // 1. Explicit path from CLI
        if ($explicitPath !== null) {
            if (!file_exists($explicitPath)) {
                throw new \RuntimeException("micro.sfx not found at: {$explicitPath}");
            }
            return $explicitPath;
        }

        // 2. Check cache
        $cachedPath = $this->getCachedPath($platform, $arch);
        if ($cachedPath !== null) {
            return $cachedPath;
        }

        // 3. Download from GitHub Release
        $this->log("No cached micro.sfx for {$platform}-{$arch}, checking GitHub releases...");
        $downloaded = $this->downloadFromRelease($platform, $arch);
        if ($downloaded !== null) {
            return $downloaded;
        }

        throw new \RuntimeException(
            "No micro.sfx binary found for {$platform}-{$arch}.\n\n" .
            "Options:\n" .
            "  1. Provide one with --micro-sfx <path>\n" .
            "  2. Trigger the 'Build Runtime' workflow in the VISU repository\n" .
            "     to create releases with pre-built binaries\n" .
            "  3. Build manually with static-php-cli:\n" .
            "     git clone https://github.com/crazywhalecc/static-php-cli /tmp/static-php-cli\n" .
            "     cd /tmp/static-php-cli && composer install\n" .
            "     bin/spc download --with-php=8.4 --for-extensions=glfw,mbstring,zip,phar\n" .
            "     bin/spc build glfw,mbstring,zip,phar --build-micro\n" .
            "  4. Cache a pre-built binary:\n" .
            "     mkdir -p ~/.visu/build-cache/{$platform}-{$arch}\n" .
            "     cp /path/to/micro.sfx ~/.visu/build-cache/{$platform}-{$arch}/micro.sfx"
        );
    }

    /**
     * Download micro.sfx from the latest VISU GitHub Release tagged runtime-*
     */
    private function downloadFromRelease(string $platform, string $arch): ?string
    {
        $assetName = "micro-{$platform}-{$arch}.sfx";

        // Find latest runtime release
        $releaseUrl = $this->findLatestRuntimeRelease();
        if ($releaseUrl === null) {
            $this->log("No runtime releases found on GitHub");
            return null;
        }

        // Find the matching asset
        $downloadUrl = $this->findAssetUrl($releaseUrl, $assetName);
        if ($downloadUrl === null) {
            $this->log("Asset {$assetName} not found in release");
            return null;
        }

        // Download and cache
        $this->log("Downloading {$assetName}...");
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
        $cachedPath = $this->cache($tempFile, $platform, $arch);
        @unlink($tempFile);

        $size = filesize($cachedPath);
        $this->log(sprintf("Downloaded and cached: %s (%.1f MB)", $cachedPath, $size / 1024 / 1024));

        return $cachedPath;
    }

    /**
     * Find the latest GitHub Release matching runtime-* tag
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
            if (isset($release['tag_name']) && str_starts_with($release['tag_name'], 'runtime-')) {
                return $release['url'] ?? null;
            }
        }

        return null;
    }

    /**
     * Find a specific asset download URL from a release
     */
    private function findAssetUrl(string $releaseApiUrl, string $assetName): ?string
    {
        $json = $this->httpGet($releaseApiUrl);
        if ($json === null) {
            return null;
        }

        $release = json_decode($json, true);
        if (!is_array($release) || !isset($release['assets'])) {
            return null;
        }

        foreach ($release['assets'] as $asset) {
            if (($asset['name'] ?? '') === $assetName) {
                return $asset['browser_download_url'] ?? null;
            }
        }

        return null;
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
     * Check the build cache for a micro.sfx binary
     */
    private function getCachedPath(string $platform, string $arch): ?string
    {
        $path = $this->cacheDir . "/{$platform}-{$arch}/micro.sfx";
        if (file_exists($path)) {
            return $path;
        }

        return null;
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
