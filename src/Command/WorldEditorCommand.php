<?php

namespace VISU\Command;

class WorldEditorCommand extends Command
{
    protected ?string $descriptionShort = 'Launch the visual world editor in your browser';

    protected $expectedArguments = [
        'host' => [
            'prefix'      => 'H',
            'longPrefix'  => 'host',
            'description' => 'Host to bind the development server to',
            'defaultValue' => '127.0.0.1',
        ],
        'port' => [
            'prefix'      => 'p',
            'longPrefix'  => 'port',
            'description' => 'Port to bind the development server to',
            'defaultValue' => 8765,
            'castTo'      => 'int',
        ],
        'ws-port' => [
            'longPrefix'  => 'ws-port',
            'description' => 'Port for the WebSocket server (live-preview)',
            'defaultValue' => 8766,
            'castTo'      => 'int',
        ],
        'no-ws' => [
            'longPrefix'  => 'no-ws',
            'description' => 'Disable the WebSocket server',
            'noValue'     => true,
        ],
    ];

    public function execute(): void
    {
        $host = (string) $this->cli->arguments->get('host');
        $port = (int) $this->cli->arguments->get('port');
        $wsPort = (int) $this->cli->arguments->get('ws-port');
        $noWs = $this->cli->arguments->defined('no-ws');

        $worldsDir = VISU_PATH_ROOT . '/worlds';
        if (!is_dir($worldsDir)) {
            mkdir($worldsDir, 0755, true);
        }

        $distDir   = __DIR__ . '/../../resources/editor/dist';
        $routerFile = __DIR__ . '/../WorldEditor/WorldEditorRouter.php';

        if (!is_dir($distDir)) {
            $this->cli->error('Editor assets not found at: ' . realpath(__DIR__ . '/../../resources/editor/dist'));
            $this->cli->out('Run <yellow>cd editor && npm install && npm run build</yellow> first.');
            return;
        }

        $url = "http://{$host}:{$port}";

        $this->info("Starting world editor at <green>{$url}</green>");
        $this->info("Worlds directory: <yellow>{$worldsDir}</yellow>");

        // Start WebSocket server in a background process
        $wsProcess = null;
        if (!$noWs) {
            $wsScript = __DIR__ . '/../WorldEditor/WebSocket/ws_server.php';
            if (file_exists($wsScript)) {
                $wsCmd = sprintf(
                    'php %s %s %d &',
                    escapeshellarg($wsScript),
                    escapeshellarg($host),
                    $wsPort
                );
                $wsProcess = @popen($wsCmd, 'r');
                $this->info("WebSocket server at <green>ws://{$host}:{$wsPort}</green>");
            } else {
                $this->info("WebSocket server script not found, skipping.", true);
            }
        }

        $this->cli->out('Press <red>Ctrl+C</red> to stop the server.');

        // Pass config to the router via environment variables
        $resourcesDir = defined('VISU_PATH_RESOURCES') ? VISU_PATH_RESOURCES : getcwd() . '/resources';
        $cacheDir = defined('VISU_PATH_CACHE') ? VISU_PATH_CACHE : getcwd() . '/var/cache';

        putenv("VISU_WORLDS_DIR={$worldsDir}");
        putenv("VISU_EDITOR_DIST={$distDir}");
        putenv("VISU_PATH_RESOURCES={$resourcesDir}");
        putenv("VISU_PATH_CACHE={$cacheDir}");

        // Try to open browser
        $os = PHP_OS_FAMILY;
        if ($os === 'Darwin') {
            exec("open {$url} &");
        } elseif ($os === 'Linux') {
            exec("xdg-open {$url} > /dev/null 2>&1 &");
        } elseif ($os === 'Windows') {
            exec("start {$url}");
        }

        $cmd = sprintf(
            'VISU_WORLDS_DIR=%s VISU_EDITOR_DIST=%s VISU_PATH_RESOURCES=%s VISU_PATH_CACHE=%s php -S %s:%d %s',
            escapeshellarg($worldsDir),
            escapeshellarg($distDir),
            escapeshellarg($resourcesDir),
            escapeshellarg($cacheDir),
            $host,
            $port,
            escapeshellarg($routerFile)
        );

        passthru($cmd);

        // Cleanup WebSocket process
        if ($wsProcess !== null && $wsProcess !== false) {
            pclose($wsProcess);
        }
    }
}
