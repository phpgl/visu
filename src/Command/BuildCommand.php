<?php

namespace VISU\Command;

use VISU\Build\BuildConfig;
use VISU\Build\GameBuilder;
use VISU\Build\StaticPhpResolver;

class BuildCommand extends Command
{
    protected ?string $descriptionShort = 'Build a distributable game package (macOS .app, Linux, Windows)';

    private const TARGETS = [
        'macos-arm64'     => ['platform' => 'macos',   'arch' => 'arm64'],
        'linux-x86_64'    => ['platform' => 'linux',   'arch' => 'x86_64'],
        'windows-x86_64'  => ['platform' => 'windows', 'arch' => 'x86_64'],
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [
        'platform' => [
            'description' => 'Target: macos-arm64, linux-x86_64, linux-arm64, windows-x86_64, macos, linux, windows, all (default: auto-detect)',
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
        'variant' => [
            'longPrefix'  => 'variant',
            'description' => 'Build variant: base or steam (default: base)',
            'defaultValue' => 'base',
        ],
        'type' => [
            'longPrefix'  => 'type',
            'description' => 'Build type as defined in build.json buildTypes (default: full)',
            'defaultValue' => 'full',
        ],
    ];

    public function execute(): void
    {
        $projectRoot = VISU_PATH_ROOT;
        $config = BuildConfig::load($projectRoot);

        $outputArg = (string) $this->cli->arguments->get('output');
        $outputDir = $outputArg !== '' ? $outputArg : $projectRoot . '/build';

        $microSfxArg = (string) $this->cli->arguments->get('micro-sfx');
        $microSfxPath = $microSfxArg !== '' ? $microSfxArg : null;

        $variant = (string) $this->cli->arguments->get('variant');
        if (!in_array($variant, ['base', 'steam'], true)) {
            $this->cli->out("<red>Error:</red> Unknown variant '{$variant}'. Use 'base' or 'steam'.");
            return;
        }

        $buildType = (string) $this->cli->arguments->get('type');
        if ($buildType !== 'full' && !isset($config->buildTypes[$buildType])) {
            $available = empty($config->buildTypes) ? '(none defined)' : implode(', ', array_keys($config->buildTypes));
            $this->cli->out("<red>Error:</red> Unknown build type '{$buildType}'. Available: full, {$available}");
            return;
        }

        $targets = $this->resolveTargets((string) $this->cli->arguments->get('platform'));

        if ($this->cli->arguments->defined('dry-run')) {
            $this->dryRun($config, $targets, $outputDir, $microSfxPath);
            return;
        }

        if (ini_get('phar.readonly')) {
            $this->cli->out('<red>Error:</red> phar.readonly is enabled. Run with:');
            $this->cli->out('  php -d phar.readonly=0 vendor/bin/visu build');
            return;
        }

        $builder = new GameBuilder($config);
        $builder->setLogger(function (string $level, string $message): void {
            match ($level) {
                'success' => $this->success($message),
                'info' => $this->info($message),
                default => $this->cli->out($message),
            };
        });

        $results = [];
        foreach ($targets as $targetName => $target) {
            $this->cli->out('');
            $this->cli->out('<bold>VISU Game Builder</bold>');
            $this->cli->out(str_repeat('-', 50));
            $this->cli->out("  Game:     {$config->name} v{$config->version}");
            $this->cli->out("  Target:   {$targetName}");
            $this->cli->out("  Variant:  {$variant}");
            $this->cli->out("  Type:     {$buildType}");
            $this->cli->out("  Output:   {$outputDir}");
            $this->cli->out(str_repeat('-', 50));
            $this->cli->out('');

            try {
                $result = $builder->build(
                    $target['platform'],
                    $outputDir,
                    $microSfxPath,
                    $target['arch'],
                    $variant,
                    $buildType,
                );
                $results[$targetName] = $result;

                $this->cli->out('');
                $this->success("{$targetName} complete!");
                $this->cli->out(sprintf('  PHAR:   %.2f MB', $result['pharSize'] / 1024 / 1024));
                $this->cli->out(sprintf('  Binary: %.2f MB', $result['binarySize'] / 1024 / 1024));
                $this->cli->out(sprintf('  Total:  %.2f MB', $result['bundleSize'] / 1024 / 1024));
                $this->cli->out('  Output: ' . $result['outputPath']);
            } catch (\Throwable $e) {
                $this->cli->out('');
                $this->cli->out("<red>{$targetName} failed:</red> " . $e->getMessage());
                if ($this->verbose) {
                    $this->cli->out($e->getTraceAsString());
                }
            }
        }

        if (count($results) > 0) {
            $this->cli->out('');
            $this->cli->out(str_repeat('=', 50));
            $this->success(sprintf('Built %d/%d targets', count($results), count($targets)));
            foreach ($results as $name => $r) {
                $this->cli->out(sprintf('  %-20s %.2f MB  %s', $name, $r['bundleSize'] / 1024 / 1024, $r['outputPath']));
            }
            $this->cli->out(str_repeat('=', 50));
        }
    }

    /**
     * Resolve platform argument to list of build targets.
     *
     * @return array<string, array{platform: string, arch: string}>
     */
    private function resolveTargets(string $platformArg): array
    {
        if ($platformArg === '' || $platformArg === 'auto') {
            $platform = StaticPhpResolver::detectPlatform();
            $arch = StaticPhpResolver::detectArch();
            $key = "{$platform}-{$arch}";
            return [$key => ['platform' => $platform, 'arch' => $arch]];
        }

        if ($platformArg === 'all') {
            return self::TARGETS;
        }

        // Exact target match: macos-arm64, linux-x86_64, etc.
        if (isset(self::TARGETS[$platformArg])) {
            return [$platformArg => self::TARGETS[$platformArg]];
        }

        // Platform-only match: "macos" → all macos targets, "linux" → all linux targets
        $matched = [];
        foreach (self::TARGETS as $name => $target) {
            if ($target['platform'] === $platformArg) {
                $matched[$name] = $target;
            }
        }

        if (!empty($matched)) {
            return $matched;
        }

        throw new \RuntimeException(
            "Unknown target: {$platformArg}\n" .
            "Available: " . implode(', ', array_keys(self::TARGETS)) . ", macos, linux, windows, all"
        );
    }

    /**
     * @param array<string, array{platform: string, arch: string}> $targets
     */
    private function dryRun(BuildConfig $config, array $targets, string $outputDir, ?string $microSfxPath): void
    {
        $this->cli->out('');
        $this->cli->out('<bold>VISU Build — Dry Run</bold>');
        $this->cli->out(str_repeat('-', 50));

        $data = $config->toArray();
        $data['output'] = $outputDir;
        $data['micro-sfx'] = $microSfxPath ?? '(auto-resolve)';
        $data['targets'] = implode(', ', array_keys($targets));

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->cli->out(sprintf('  <blue>%-25s</blue> %s', $key, json_encode($value)));
            } else {
                $this->cli->out(sprintf('  <blue>%-25s</blue> %s', $key, $value));
            }
        }

        $this->cli->out('');
        $resolver = new StaticPhpResolver();
        foreach ($targets as $name => $target) {
            try {
                $sfxPath = $resolver->resolve($microSfxPath, $target['platform'], $target['arch']);
                $this->success("{$name}: micro.sfx found at {$sfxPath}");
            } catch (\RuntimeException $e) {
                $this->cli->out("<yellow>{$name}:</yellow> " . explode("\n", $e->getMessage())[0]);
            }
        }

        $this->cli->out(str_repeat('-', 50));
    }
}
