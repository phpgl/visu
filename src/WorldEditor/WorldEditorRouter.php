<?php

/**
 * PHP built-in server router for the VISU World Editor.
 *
 * Environment variables:
 *   VISU_WORLDS_DIR  - path to the worlds directory
 *   VISU_EDITOR_DIST - path to the compiled Vue SPA dist/ directory
 */

// Bootstrap Composer autoloader — works whether VISU is the root project or a dependency
$autoloadPaths = [
    __DIR__ . '/../../../autoload.php',   // as a Composer dependency (vendor/phpgl/visu/src/WorldEditor/)
    __DIR__ . '/../../vendor/autoload.php', // as the root project
];
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

$worldsDir = getenv('VISU_WORLDS_DIR') ?: (getcwd() . '/worlds');
$distDir   = getenv('VISU_EDITOR_DIST') ?: (__DIR__ . '/../../resources/editor/dist');

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Strip query string for routing
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path)) {
    $path = '/';
}

if (strpos($path, '/api/') === 0) {
    require_once __DIR__ . '/Api/WorldsController.php';

    $resourcesDir = getenv('VISU_PATH_RESOURCES') ?: (getcwd() . '/resources');
    $cacheDir = getenv('VISU_PATH_CACHE') ?: (getcwd() . '/var/cache');
    $controller = new \VISU\WorldEditor\Api\WorldsController($worldsDir, $resourcesDir, $cacheDir);
    $controller->handle($method, $path);
    return true;
}

// Serve static files from dist/
$filePath = rtrim($distDir, '/') . $path;

if ($path !== '/' && file_exists($filePath) && is_file($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'js'   => 'application/javascript',
        'css'  => 'text/css',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }

    readfile($filePath);
    return true;
}

// SPA fallback — serve index.html
$indexFile = rtrim($distDir, '/') . '/index.html';
if (file_exists($indexFile)) {
    header('Content-Type: text/html');
    readfile($indexFile);
    return true;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
