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
    public function package(string $binaryPath, string $outputDir, string $platform): string
    {
        return match ($platform) {
            'macos' => $this->packageMacOS($binaryPath, $outputDir),
            'windows' => $this->packageFlat($binaryPath, $outputDir, '.exe'),
            'linux' => $this->packageFlat($binaryPath, $outputDir, ''),
            default => throw new \RuntimeException("Unsupported platform: {$platform}"),
        };
    }

    private function packageMacOS(string $binaryPath, string $outputDir): string
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

    private function packageFlat(string $binaryPath, string $outputDir, string $extension): string
    {
        $name = $this->config->name;
        $dir = $outputDir . '/' . $name;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $targetName = $name . $extension;
        copy($binaryPath, $dir . '/' . $targetName);
        chmod($dir . '/' . $targetName, 0755);

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

    private function copyExternalResources(string $targetDir): void
    {
        foreach ($this->config->externalResources as $resourcePath) {
            $src = $this->config->projectRoot . '/' . $resourcePath;
            if (!is_dir($src)) continue;

            $dst = $targetDir . '/' . $resourcePath;
            if (!is_dir($dst)) {
                mkdir($dst, 0755, true);
            }

            $cmd = sprintf(
                'rsync -a %s %s',
                escapeshellarg($src . '/'),
                escapeshellarg($dst . '/')
            );
            exec($cmd);
        }
    }
}
