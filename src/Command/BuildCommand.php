<?php

namespace VISU\Command;

use VISU\Build\BuildConfig;
use VISU\Build\GameBuilder;
use VISU\Build\StaticPhpResolver;

class BuildCommand extends Command
{
    protected ?string $descriptionShort = 'Build a distributable game package (macOS .app, Windows, Linux)';

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [
        'platform' => [
            'description' => 'Target platform: macos, windows, linux (default: auto-detect)',
            'defaultValue' => '',
        ],
        'dry-run' => [
            'longPrefix'  => 'dry-run',
            'description' => 'Show resolved configuration without building',
            'noValue'     => true,
        ],
        'micro-sfx' => [
            'longPrefix'  => 'micro-sfx',
            'description' => 'Path to a pre-built micro.sfx binary',
            'defaultValue' => '',
        ],
        'output' => [
            'prefix'      => 'o',
            'longPrefix'  => 'output',
            'description' => 'Output directory (default: build/)',
            'defaultValue' => '',
        ],
    ];

    public function execute(): void
    {
        $projectRoot = VISU_PATH_ROOT;
        $config = BuildConfig::load($projectRoot);

        // Resolve platform
        $platformArg = (string) $this->cli->arguments->get('platform');
        $platform = $platformArg !== '' ? $platformArg : StaticPhpResolver::detectPlatform();
        $arch = StaticPhpResolver::detectArch();

        // Output directory
        $outputArg = (string) $this->cli->arguments->get('output');
        $outputDir = $outputArg !== '' ? $outputArg : $projectRoot . '/build';

        // micro.sfx path
        $microSfxArg = (string) $this->cli->arguments->get('micro-sfx');
        $microSfxPath = $microSfxArg !== '' ? $microSfxArg : null;

        // Dry-run: show config and exit
        if ($this->cli->arguments->defined('dry-run')) {
            $this->dryRun($config, $platform, $arch, $outputDir, $microSfxPath);
            return;
        }

        // Check phar.readonly
        if (ini_get('phar.readonly')) {
            $this->cli->out('<red>Error:</red> phar.readonly is enabled. Run with:');
            $this->cli->out('  php -d phar.readonly=0 vendor/bin/visu build ' . $platform);
            return;
        }

        $this->cli->out('');
        $this->cli->out('<bold>VISU Game Builder</bold>');
        $this->cli->out(str_repeat('-', 50));
        $this->cli->out("  Game:     {$config->name} v{$config->version}");
        $this->cli->out("  Platform: {$platform}-{$arch}");
        $this->cli->out("  Output:   {$outputDir}");
        $this->cli->out(str_repeat('-', 50));
        $this->cli->out('');

        $builder = new GameBuilder($config);
        $builder->setLogger(function (string $level, string $message): void {
            match ($level) {
                'success' => $this->success($message),
                'info' => $this->info($message),
                default => $this->cli->out($message),
            };
        });

        try {
            $result = $builder->build($platform, $outputDir, $microSfxPath);

            $this->cli->out('');
            $this->cli->out(str_repeat('=', 50));
            $this->success('Build complete!');
            $this->cli->out(sprintf('  PHAR:   %.2f MB', $result['pharSize'] / 1024 / 1024));
            $this->cli->out(sprintf('  Binary: %.2f MB', $result['binarySize'] / 1024 / 1024));
            $this->cli->out(sprintf('  Total:  %.2f MB', $result['bundleSize'] / 1024 / 1024));
            $this->cli->out('  Output: ' . $result['outputPath']);
            $this->cli->out(str_repeat('=', 50));
        } catch (\Throwable $e) {
            $this->cli->out('');
            $this->cli->out('<red>Build failed:</red> ' . $e->getMessage());
            if ($this->verbose) {
                $this->cli->out($e->getTraceAsString());
            }
        }
    }

    private function dryRun(BuildConfig $config, string $platform, string $arch, string $outputDir, ?string $microSfxPath): void
    {
        $this->cli->out('');
        $this->cli->out('<bold>VISU Build — Dry Run</bold>');
        $this->cli->out(str_repeat('-', 50));

        $data = $config->toArray();
        $data['target.platform'] = $platform;
        $data['target.arch'] = $arch;
        $data['output'] = $outputDir;
        $data['micro-sfx'] = $microSfxPath ?? '(auto-resolve)';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->cli->out(sprintf('  <blue>%-25s</blue> %s', $key, json_encode($value)));
            } else {
                $this->cli->out(sprintf('  <blue>%-25s</blue> %s', $key, $value));
            }
        }

        // Check micro.sfx availability
        $this->cli->out('');
        $resolver = new StaticPhpResolver();
        try {
            $sfxPath = $resolver->resolve($microSfxPath, $platform, $arch);
            $this->success("micro.sfx found: {$sfxPath}");
        } catch (\RuntimeException $e) {
            $this->cli->out('<yellow>Warning:</yellow> ' . explode("\n", $e->getMessage())[0]);
        }

        $this->cli->out(str_repeat('-', 50));
    }
}
