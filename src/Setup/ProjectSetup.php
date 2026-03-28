<?php

namespace VISU\Setup;

/**
 * Interactive project setup for VISU engine.
 *
 * Checks and creates all required project structure when VISU is used as a
 * Composer dependency. Designed to be safe for repeated runs — existing files
 * are never overwritten without explicit confirmation.
 */
class ProjectSetup
{
    /**
     * Whether to run interactively (prompt user) or silently (skip existing).
     */
    private bool $interactive;

    /**
     * Callable for output (receives a single string line).
     * @var callable(string): void
     */
    private $output;

    /**
     * Callable for yes/no prompts (receives question string, returns bool).
     * @var callable(string): bool
     */
    private $confirm;

    /**
     * Tracks what was created for the summary.
     * @var array<string>
     */
    private array $created = [];

    /**
     * Tracks what was skipped.
     * @var array<string>
     */
    private array $skipped = [];

    /**
     * @param string $projectRoot Absolute path to the consumer project root
     * @param bool $interactive Whether to prompt user for decisions
     * @param callable(string): void $output Output callback
     * @param callable(string): bool $confirm Confirmation callback
     */
    public function __construct(
        private readonly string $projectRoot,
        bool $interactive = true,
        ?callable $output = null,
        ?callable $confirm = null,
    ) {
        $this->interactive = $interactive;
        $this->output = $output ?? function (string $line): void {
            echo $line . PHP_EOL;
        };
        $this->confirm = $confirm ?? function (string $question): bool {
            echo $question . ' [Y/n] ';
            $line = trim((string) fgets(STDIN));
            return $line === '' || strtolower($line) === 'y';
        };
    }

    /**
     * Run the full setup process.
     *
     * @return bool True if any files/directories were created
     */
    public function run(): bool
    {
        $this->out('');
        $this->out('  VISU Engine — Project Setup');
        $this->out('  ==========================');
        $this->out('');

        $this->ensureDirectories();
        $this->ensureAppCtn();
        $this->ensureBootstrapEntry();
        $this->ensureClaudeMd();
        $this->ensureGitignoreEntries();

        $this->out('');
        $this->printSummary();

        return count($this->created) > 0;
    }

    /**
     * Ensure all required directories exist.
     */
    private function ensureDirectories(): void
    {
        $dirs = [
            'var/cache'  => 'Container cache',
            'var/store'  => 'Persistent storage (saves, etc.)',
            'resources'  => 'Application resources (shaders, textures, scenes)',
            'resources/shader' => 'Application shaders',
        ];

        foreach ($dirs as $relPath => $description) {
            $absPath = $this->projectRoot . DIRECTORY_SEPARATOR . $relPath;
            if (is_dir($absPath)) {
                $this->skip($relPath, 'directory exists');
                continue;
            }
            mkdir($absPath, 0777, true);
            $this->created($relPath . '/', $description);
        }
    }

    /**
     * Ensure app.ctn container config exists.
     */
    private function ensureAppCtn(): void
    {
        $file = $this->projectRoot . DIRECTORY_SEPARATOR . 'app.ctn';
        if (file_exists($file)) {
            $this->skip('app.ctn', 'already exists');
            return;
        }

        $content = <<<'CTN'
/**
 * VISU application container configuration.
 *
 * Register your game's services, commands and systems here.
 * @see https://container.clancats.com/
 */

CTN;
        file_put_contents($file, $content);
        $this->created('app.ctn', 'DI container config');
    }

    /**
     * Ensure a bootstrap/entry point script exists.
     */
    private function ensureBootstrapEntry(): void
    {
        $file = $this->projectRoot . DIRECTORY_SEPARATOR . 'game.php';
        if (file_exists($file)) {
            $this->skip('game.php', 'already exists');
            return;
        }

        if (!$this->shouldCreate('game.php', 'Application entry point')) {
            return;
        }

        $content = <<<'PHP'
<?php
/**
 * VISU Game Entry Point
 *
 * This is the main entry point for your game application.
 * Run with: php game.php
 */
if (!defined('DS')) { define('DS', DIRECTORY_SEPARATOR); }

define('VISU_PATH_ROOT', __DIR__);
define('VISU_PATH_CACHE', __DIR__ . DS . 'var' . DS . 'cache');
define('VISU_PATH_STORE', __DIR__ . DS . 'var' . DS . 'store');
define('VISU_PATH_RESOURCES', __DIR__ . DS . 'resources');
define('VISU_PATH_APPCONFIG', __DIR__);

require __DIR__ . DS . 'vendor' . DS . 'autoload.php';

$container = require __DIR__ . DS . 'vendor' . DS . 'phpgl' . DS . 'visu' . DS . 'bootstrap.php';

// Your game initialization goes here.
// Example:
// $app = new \VISU\Quickstart();
// $app->run();

PHP;
        file_put_contents($file, $content);
        $this->created('game.php', 'Application entry point');
    }

    /**
     * Ensure CLAUDE.md exists with VISU context.
     */
    private function ensureClaudeMd(): void
    {
        $file = $this->projectRoot . DIRECTORY_SEPARATOR . 'CLAUDE.md';
        if (file_exists($file)) {
            $this->skip('CLAUDE.md', 'already exists');
            return;
        }

        if (!$this->shouldCreate('CLAUDE.md', 'Claude Code project instructions')) {
            return;
        }

        $content = <<<'MD'
# Game Project — powered by VISU Engine

> This project uses the VISU PHP Game Engine as a Composer dependency.
> Engine source lives in `vendor/phpgl/visu/` — refer to its CLAUDE.md for engine internals.

---

## Project Structure

```
project-root/
  game.php              # Application entry point
  app.ctn               # DI container config (ClanCats Container)
  composer.json          # Dependencies
  resources/             # Game resources (shaders, textures, scenes, UI)
    shader/              # Application shaders
  var/
    cache/               # Container cache (auto-generated)
    store/               # Persistent storage (saves, etc.)
  vendor/
    phpgl/visu/          # VISU Engine
```

## Key Commands

```bash
# Run the game
php game.php

# VISU CLI (commands, editor, transpiler)
./vendor/bin/visu

# Available VISU commands
./vendor/bin/visu commands:available

# Start the World Editor
./vendor/bin/visu world-editor

# Transpile scenes/UI to PHP factories
./vendor/bin/visu transpile
```

## Engine Reference

For engine internals, architecture, and coding conventions see:
`vendor/phpgl/visu/CLAUDE.md`

Key engine concepts:
- **ECS**: EntityRegistry, Components, Systems (`VISU\ECS\`)
- **Scenes**: JSON scene files loaded via SceneLoader (`VISU\Scene\`)
- **UI**: JSON UI layouts interpreted by UIInterpreter (`VISU\UI\`)
- **Signals**: Event dispatching via Dispatcher (`VISU\Signal\`)
- **Audio**: AudioManager with channels (`VISU\Audio\`)
- **Save/Load**: SaveManager with slots and migrations (`VISU\Save\`)
- **Graphics**: OpenGL 4.1 rendering pipeline (`VISU\Graphics\`)

## Conventions

- Namespace: Use your own project namespace (configured in composer.json)
- Game logic: Components + Systems pattern, communicate via Signals
- Scenes: JSON format in `resources/scenes/`
- UI layouts: JSON format in `resources/ui/`
- Saves: Managed by SaveManager, stored in `var/store/`
MD;
        file_put_contents($file, $content);
        $this->created('CLAUDE.md', 'Claude Code project instructions');
    }

    /**
     * Ensure .gitignore has required entries.
     */
    private function ensureGitignoreEntries(): void
    {
        $file = $this->projectRoot . DIRECTORY_SEPARATOR . '.gitignore';
        $requiredEntries = [
            '/vendor/',
            '/var/',
            '.DS_Store',
        ];

        $existingContent = '';
        if (file_exists($file)) {
            $existingContent = file_get_contents($file) ?: '';
        }

        $existingLines = array_map('trim', explode("\n", $existingContent));
        $missing = [];

        foreach ($requiredEntries as $entry) {
            if (!in_array($entry, $existingLines, true)) {
                $missing[] = $entry;
            }
        }

        if (empty($missing)) {
            $this->skip('.gitignore', 'all entries present');
            return;
        }

        if (file_exists($file)) {
            // Append missing entries
            $append = PHP_EOL . '# VISU Engine' . PHP_EOL;
            foreach ($missing as $entry) {
                $append .= $entry . PHP_EOL;
            }
            file_put_contents($file, $existingContent . $append);
            $this->created('.gitignore', 'added ' . count($missing) . ' missing entries');
        } else {
            $content = '# VISU Engine' . PHP_EOL;
            foreach ($requiredEntries as $entry) {
                $content .= $entry . PHP_EOL;
            }
            file_put_contents($file, $content);
            $this->created('.gitignore', 'created with defaults');
        }
    }

    /**
     * Ask user whether to create a file (in interactive mode).
     */
    private function shouldCreate(string $relPath, string $description): bool
    {
        if (!$this->interactive) {
            return true;
        }
        return ($this->confirm)("  Create {$relPath} ({$description})?");
    }

    private function created(string $relPath, string $description): void
    {
        $this->created[] = $relPath;
        $this->out("  + {$relPath}  ({$description})");
    }

    private function skip(string $relPath, string $reason): void
    {
        $this->skipped[] = $relPath;
    }

    private function out(string $line): void
    {
        ($this->output)($line);
    }

    private function printSummary(): void
    {
        if (count($this->created) === 0) {
            $this->out('  Everything up to date — nothing to create.');
        } else {
            $this->out('  Created ' . count($this->created) . ' item(s), '
                . count($this->skipped) . ' already existed.');
        }
        $this->out('');
    }

    /**
     * Get the list of created items.
     * @return array<string>
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    /**
     * Get the list of skipped items.
     * @return array<string>
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }
}
