<?php

namespace VISU\Command;

use VISU\ECS\ComponentRegistry;
use VISU\Transpiler\PrefabTranspiler;
use VISU\Transpiler\SceneTranspiler;
use VISU\Transpiler\TranspilerRegistry;
use VISU\Transpiler\UITranspiler;

class TranspileCommand extends Command
{
    protected ?string $descriptionShort = 'Transpile scene, UI, and prefab JSON files to PHP factories';

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [
        'force' => [
            'prefix'      => 'f',
            'longPrefix'  => 'force',
            'description' => 'Force re-transpile all files, ignoring the hash registry',
            'noValue'     => true,
        ],
        'scenes' => [
            'longPrefix'  => 'scenes',
            'description' => 'Directory containing scene JSON files (default: resources/scenes)',
            'defaultValue' => '',
        ],
        'ui' => [
            'longPrefix'  => 'ui',
            'description' => 'Directory containing UI JSON files (default: resources/ui)',
            'defaultValue' => '',
        ],
        'prefabs' => [
            'longPrefix'  => 'prefabs',
            'description' => 'Directory containing prefab JSON files (default: resources/prefabs)',
            'defaultValue' => '',
        ],
        'output' => [
            'prefix'      => 'o',
            'longPrefix'  => 'output',
            'description' => 'Output directory for generated PHP files (default: var/cache/transpiled)',
            'defaultValue' => '',
        ],
    ];

    public function __construct(
        private ComponentRegistry $componentRegistry,
    ) {
    }

    public function execute(): void
    {
        $resourcesDir = VISU_PATH_RESOURCES;
        $cacheDir = VISU_PATH_CACHE;

        $scenesArg  = (string) $this->cli->arguments->get('scenes');
        $uiArg      = (string) $this->cli->arguments->get('ui');
        $prefabsArg = (string) $this->cli->arguments->get('prefabs');
        $outputArg  = (string) $this->cli->arguments->get('output');

        $scenesDir  = $scenesArg !== '' ? $scenesArg : $resourcesDir . '/scenes';
        $uiDir      = $uiArg !== '' ? $uiArg : $resourcesDir . '/ui';
        $prefabsDir = $prefabsArg !== '' ? $prefabsArg : $resourcesDir . '/prefabs';
        $outputDir  = $outputArg !== '' ? $outputArg : $cacheDir . '/transpiled';
        $force      = $this->cli->arguments->defined('force');

        $registry = new TranspilerRegistry($cacheDir);

        $sceneTranspiler  = new SceneTranspiler($this->componentRegistry);
        $uiTranspiler     = new UITranspiler();
        $prefabTranspiler = new PrefabTranspiler($this->componentRegistry);

        $stats = ['transpiled' => 0, 'skipped' => 0, 'errors' => 0];

        // Transpile scenes
        if (is_dir($scenesDir)) {
            $this->info("Scanning scenes: {$scenesDir}");
            $this->transpileDirectory(
                $scenesDir,
                $outputDir . '/Scenes',
                'VISU\\Generated\\Scenes',
                $sceneTranspiler,
                $registry,
                $force,
                $stats,
            );
        } else {
            $this->info("Scenes directory not found: {$scenesDir}", true);
        }

        // Transpile UI layouts
        if (is_dir($uiDir)) {
            $this->info("Scanning UI layouts: {$uiDir}");
            $this->transpileDirectory(
                $uiDir,
                $outputDir . '/UI',
                'VISU\\Generated\\UI',
                $uiTranspiler,
                $registry,
                $force,
                $stats,
            );
        } else {
            $this->info("UI directory not found: {$uiDir}", true);
        }

        // Transpile prefabs
        if (is_dir($prefabsDir)) {
            $this->info("Scanning prefabs: {$prefabsDir}");
            $this->transpileDirectory(
                $prefabsDir,
                $outputDir . '/Prefabs',
                'VISU\\Generated\\Prefabs',
                $prefabTranspiler,
                $registry,
                $force,
                $stats,
            );
        } else {
            $this->info("Prefabs directory not found: {$prefabsDir}", true);
        }

        $registry->save();

        $this->cli->out('');
        if ($stats['errors'] > 0) {
            $this->cli->out(sprintf(
                '[<red>done</red>] %d transpiled, %d skipped, <red>%d errors</red>',
                $stats['transpiled'],
                $stats['skipped'],
                $stats['errors'],
            ));
        } else {
            $this->success(sprintf(
                '%d transpiled, %d skipped, 0 errors',
                $stats['transpiled'],
                $stats['skipped'],
            ), false, 'done');
        }
    }

    /**
     * @param SceneTranspiler|UITranspiler|PrefabTranspiler $transpiler
     * @param array{transpiled: int, skipped: int, errors: int} $stats
     */
    private function transpileDirectory(
        string $sourceDir,
        string $outputDir,
        string $namespace,
        object $transpiler,
        TranspilerRegistry $registry,
        bool $force,
        array &$stats,
    ): void {
        $files = glob($sourceDir . '/*.json');
        if ($files === false || count($files) === 0) {
            $this->info('  No JSON files found.', true);
            return;
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($files as $jsonPath) {
            $baseName = pathinfo($jsonPath, PATHINFO_FILENAME);
            $className = $this->toClassName($baseName);
            $outputPath = $outputDir . '/' . $className . '.php';

            if (!$force && !$registry->needsUpdate($jsonPath)) {
                $this->info("  skip {$baseName} (unchanged)", true);
                $stats['skipped']++;
                continue;
            }

            try {
                $code = $transpiler->transpile($jsonPath, $className, $namespace);
                file_put_contents($outputPath, $code);
                $registry->record($jsonPath, $outputPath);
                $this->success("  {$baseName} -> {$className}.php");
                $stats['transpiled']++;
            } catch (\Throwable $e) {
                $this->cli->out("[<red>error</red>] {$baseName}: {$e->getMessage()}");
                $stats['errors']++;
            }
        }
    }

    /**
     * Converts a file basename like "office_level1" to a PascalCase class name "OfficeLevel1".
     */
    private function toClassName(string $baseName): string
    {
        // Replace non-alphanumeric with space, then ucwords, then remove spaces
        $cleaned = preg_replace('/[^a-zA-Z0-9]+/', ' ', $baseName) ?? $baseName;
        return str_replace(' ', '', ucwords($cleaned));
    }
}
