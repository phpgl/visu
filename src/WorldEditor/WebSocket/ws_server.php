<?php

/**
 * Standalone WebSocket server entry point for VISU World Editor.
 *
 * Usage: php ws_server.php [host] [port]
 *
 * Started automatically by the `bin/visu world-editor` command.
 */

// Bootstrap Composer autoloader
$autoloadPaths = [
    __DIR__ . '/../../../../autoload.php',      // as a Composer dependency
    __DIR__ . '/../../../vendor/autoload.php',   // as the root project
];
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 8766);

$changeFile = (getenv('VISU_PATH_CACHE') ?: getcwd() . '/var/cache') . '/.visu_changes';

$bridge = new \VISU\WorldEditor\WebSocket\EditorBridge($host, $port, $changeFile);
$bridge->run();
