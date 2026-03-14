<?php

namespace VISU\WorldEditor\Api;

use VISU\WorldEditor\WorldFile;

class WorldsController
{
    private string $resourcesDir;

    /** @var string|null Path to transpile cache directory */
    private ?string $cacheDir;

    public function __construct(
        private string $worldsDir,
        ?string $resourcesDir = null,
        ?string $cacheDir = null,
    ) {
        $this->resourcesDir = $resourcesDir ?? (defined('VISU_PATH_RESOURCES') ? VISU_PATH_RESOURCES : getcwd() . '/resources');
        $this->cacheDir = $cacheDir ?? (defined('VISU_PATH_CACHE') ? VISU_PATH_CACHE : null);
    }

    public function handle(string $method, string $path): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($method === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        // /api/config
        if ($path === '/api/config') {
            echo json_encode([
                'tileSize' => 32,
                'gridWidth' => 32,
                'gridHeight' => 32,
                'worldsDir' => $this->worldsDir,
            ]);
            return;
        }

        // /api/worlds
        if ($path === '/api/worlds' && $method === 'GET') {
            $this->listWorlds();
            return;
        }

        // /api/worlds/{name}/entities/{layerId}/{entityId} — must match before /api/worlds/{name}
        if (preg_match('#^/api/worlds/([a-zA-Z0-9_\-]+)/entities/([a-zA-Z0-9_\-]+)/([0-9]+)$#', $path, $m)) {
            match ($method) {
                'PATCH'  => $this->patchEntity($m[1], $m[2], (int) $m[3]),
                'DELETE' => $this->deleteEntity($m[1], $m[2], (int) $m[3]),
                default  => $this->error(405, 'Method not allowed'),
            };
            return;
        }

        // /api/worlds/{name}
        if (preg_match('#^/api/worlds/([a-zA-Z0-9_\-]+)$#', $path, $m)) {
            $name = $m[1];
            match ($method) {
                'GET'    => $this->getWorld($name),
                'POST'   => $this->saveWorld($name),
                'DELETE' => $this->deleteWorld($name),
                default  => $this->error(405, 'Method not allowed'),
            };
            return;
        }

        // /api/assets
        if ($path === '/api/assets' && $method === 'GET') {
            $this->browseAssets('');
            return;
        }

        // /api/assets/browse?dir=path
        if ($path === '/api/assets/browse' && $method === 'GET') {
            $this->browseAssets($_GET['dir'] ?? '');
            return;
        }

        // /api/scenes
        if ($path === '/api/scenes' && $method === 'GET') {
            $this->listScenes();
            return;
        }

        // /api/scenes/{name}
        if (preg_match('#^/api/scenes/([a-zA-Z0-9_\-]+)$#', $path, $m)) {
            match ($method) {
                'GET'  => $this->getScene($m[1]),
                'POST' => $this->saveScene($m[1]),
                default => $this->error(405, 'Method not allowed'),
            };
            return;
        }

        // /api/ui
        if ($path === '/api/ui' && $method === 'GET') {
            $this->listUILayouts();
            return;
        }

        // /api/ui/{name}
        if (preg_match('#^/api/ui/([a-zA-Z0-9_\-]+)$#', $path, $m)) {
            match ($method) {
                'GET'  => $this->getUILayout($m[1]),
                'POST' => $this->saveUILayout($m[1]),
                default => $this->error(405, 'Method not allowed'),
            };
            return;
        }

        // /api/transpile
        if ($path === '/api/transpile' && $method === 'POST') {
            $this->transpileAll();
            return;
        }

        $this->error(404, 'API endpoint not found');
    }

    // ── World CRUD ──────────────────────────────────────────────────────

    private function listWorlds(): void
    {
        $worlds = [];
        if (is_dir($this->worldsDir)) {
            foreach (glob($this->worldsDir . '/*.world.json') ?: [] as $file) {
                $name = basename($file, '.world.json');
                $worlds[] = [
                    'name'     => $name,
                    'modified' => date('c', filemtime($file) ?: 0),
                    'size'     => filesize($file),
                ];
            }
        }
        echo json_encode($worlds);
    }

    private function getWorld(string $name): void
    {
        $path = $this->worldPath($name);
        if (!file_exists($path)) {
            $this->error(404, "World '{$name}' not found");
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->error(500, 'Failed to read world file');
            return;
        }

        echo $content;
    }

    private function saveWorld(string $name): void
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            $this->error(400, 'Empty request body');
            return;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->error(400, 'Invalid JSON body');
            return;
        }

        try {
            $world = WorldFile::fromArray($data);
            $world->save($this->worldPath($name));
            echo json_encode(['ok' => true, 'name' => $name]);
        } catch (\Throwable $e) {
            $this->error(500, $e->getMessage());
        }
    }

    private function deleteWorld(string $name): void
    {
        $path = $this->worldPath($name);
        if (!file_exists($path)) {
            $this->error(404, "World '{$name}' not found");
            return;
        }

        if (!unlink($path)) {
            $this->error(500, 'Failed to delete world file');
            return;
        }

        echo json_encode(['ok' => true]);
    }

    // ── Entity PATCH/DELETE ─────────────────────────────────────────────

    private function patchEntity(string $worldName, string $layerId, int $entityId): void
    {
        $path = $this->worldPath($worldName);
        if (!file_exists($path)) {
            $this->error(404, "World '{$worldName}' not found");
            return;
        }

        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            $this->error(400, 'Empty request body');
            return;
        }

        $patch = json_decode($body, true);
        if (!is_array($patch)) {
            $this->error(400, 'Invalid JSON body');
            return;
        }

        try {
            $world = WorldFile::load($path);
            $found = false;

            foreach ($world->layers as &$layer) {
                if ($layer['id'] !== $layerId || ($layer['type'] ?? '') !== 'entity') {
                    continue;
                }
                foreach ($layer['entities'] as &$entity) {
                    if (($entity['id'] ?? 0) === $entityId) {
                        foreach ($patch as $key => $value) {
                            if ($key === 'id') continue;
                            $entity[$key] = $value;
                        }
                        $found = true;
                        break 2;
                    }
                }
                unset($entity);
            }
            unset($layer);

            if (!$found) {
                $this->error(404, "Entity {$entityId} not found in layer '{$layerId}'");
                return;
            }

            $world->save($path);
            echo json_encode(['ok' => true, 'entityId' => $entityId]);
        } catch (\Throwable $e) {
            $this->error(500, $e->getMessage());
        }
    }

    private function deleteEntity(string $worldName, string $layerId, int $entityId): void
    {
        $path = $this->worldPath($worldName);
        if (!file_exists($path)) {
            $this->error(404, "World '{$worldName}' not found");
            return;
        }

        try {
            $world = WorldFile::load($path);
            $found = false;

            foreach ($world->layers as &$layer) {
                if ($layer['id'] !== $layerId || ($layer['type'] ?? '') !== 'entity') {
                    continue;
                }
                $before = count($layer['entities'] ?? []);
                $layer['entities'] = array_values(array_filter(
                    $layer['entities'] ?? [],
                    fn($e) => ($e['id'] ?? 0) !== $entityId
                ));
                if (count($layer['entities']) < $before) {
                    $found = true;
                }
                break;
            }
            unset($layer);

            if (!$found) {
                $this->error(404, "Entity {$entityId} not found in layer '{$layerId}'");
                return;
            }

            $world->save($path);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            $this->error(500, $e->getMessage());
        }
    }

    // ── Asset Browser ───────────────────────────────────────────────────

    private function browseAssets(string $subDir): void
    {
        $subDir = str_replace(['..', "\0"], '', $subDir);
        $subDir = ltrim($subDir, '/');

        $baseDir = $this->resourcesDir;
        $fullDir = $subDir !== '' ? $baseDir . '/' . $subDir : $baseDir;

        if (!is_dir($fullDir)) {
            echo json_encode(['path' => $subDir, 'entries' => []]);
            return;
        }

        $entries = [];
        $items = scandir($fullDir);
        if ($items === false) {
            echo json_encode(['path' => $subDir, 'entries' => []]);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $itemPath = $fullDir . '/' . $item;
            $relativePath = $subDir !== '' ? $subDir . '/' . $item : $item;

            if (is_dir($itemPath)) {
                $entries[] = [
                    'name' => $item,
                    'path' => $relativePath,
                    'type' => 'directory',
                ];
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                $assetType = match ($ext) {
                    'png', 'jpg', 'jpeg', 'bmp', 'gif', 'webp' => 'image',
                    'json' => 'json',
                    'glsl', 'vert', 'frag' => 'shader',
                    'glb', 'gltf' => 'model',
                    'ogg', 'wav', 'mp3' => 'audio',
                    'ttf', 'otf' => 'font',
                    default => 'file',
                };

                $entries[] = [
                    'name' => $item,
                    'path' => $relativePath,
                    'type' => $assetType,
                    'size' => filesize($itemPath),
                ];
            }
        }

        usort($entries, function ($a, $b) {
            if ($a['type'] === 'directory' && $b['type'] !== 'directory') return -1;
            if ($a['type'] !== 'directory' && $b['type'] === 'directory') return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        echo json_encode(['path' => $subDir, 'entries' => $entries]);
    }

    // ── Scene API ───────────────────────────────────────────────────────

    private function listScenes(): void
    {
        $scenes = [];
        $scenesDir = $this->resourcesDir . '/scenes';

        if (is_dir($scenesDir)) {
            foreach (glob($scenesDir . '/*.json') ?: [] as $file) {
                $name = basename($file, '.json');
                $scenes[] = [
                    'name'     => $name,
                    'modified' => date('c', filemtime($file) ?: 0),
                    'size'     => filesize($file),
                ];
            }
        }

        echo json_encode($scenes);
    }

    private function getScene(string $name): void
    {
        $path = $this->resourcesDir . '/scenes/' . $name . '.json';
        if (!file_exists($path)) {
            $this->error(404, "Scene '{$name}' not found");
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->error(500, 'Failed to read scene file');
            return;
        }

        echo $content;
    }

    private function saveScene(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            $this->error(400, 'Invalid scene name');
            return;
        }

        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            $this->error(400, 'Empty request body');
            return;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->error(400, 'Invalid JSON body');
            return;
        }

        $scenesDir = $this->resourcesDir . '/scenes';
        if (!is_dir($scenesDir)) {
            mkdir($scenesDir, 0755, true);
        }

        $path = $scenesDir . '/' . $name . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json) === false) {
            $this->error(500, 'Failed to save scene');
            return;
        }

        // Auto-transpile scene
        $transpileResult = $this->autoTranspileScene($path);

        echo json_encode(['ok' => true, 'name' => $name, 'transpiled' => $transpileResult]);
    }

    // ── UI Layout API ────────────────────────────────────────────────────

    private function listUILayouts(): void
    {
        $layouts = [];
        $uiDir = $this->resourcesDir . '/ui';

        if (is_dir($uiDir)) {
            foreach (glob($uiDir . '/*.json') ?: [] as $file) {
                $name = basename($file, '.json');
                $layouts[] = [
                    'name'     => $name,
                    'modified' => date('c', filemtime($file) ?: 0),
                    'size'     => filesize($file),
                ];
            }
        }

        echo json_encode($layouts);
    }

    private function getUILayout(string $name): void
    {
        $path = $this->resourcesDir . '/ui/' . $name . '.json';
        if (!file_exists($path)) {
            $this->error(404, "UI layout '{$name}' not found");
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->error(500, 'Failed to read UI layout file');
            return;
        }

        echo $content;
    }

    private function saveUILayout(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            $this->error(400, 'Invalid UI layout name');
            return;
        }

        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            $this->error(400, 'Empty request body');
            return;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->error(400, 'Invalid JSON body');
            return;
        }

        $uiDir = $this->resourcesDir . '/ui';
        if (!is_dir($uiDir)) {
            mkdir($uiDir, 0755, true);
        }

        $path = $uiDir . '/' . $name . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json) === false) {
            $this->error(500, 'Failed to save UI layout');
            return;
        }

        // Auto-transpile UI layout
        $transpileResult = $this->autoTranspileUI($path);

        echo json_encode(['ok' => true, 'name' => $name, 'transpiled' => $transpileResult]);
    }

    // ── Auto-Transpile ──────────────────────────────────────────────────

    /**
     * @return array{success: bool, output?: string, error?: string}|null
     */
    private function autoTranspileScene(string $jsonPath): ?array
    {
        if ($this->cacheDir === null) {
            return null;
        }

        try {
            $transpiler = new \VISU\Transpiler\SceneTranspiler(new \VISU\ECS\ComponentRegistry());
            $baseName = pathinfo($jsonPath, PATHINFO_FILENAME);
            $className = $this->toClassName($baseName);
            $outputDir = $this->cacheDir . '/transpiled/Scenes';

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputPath = $outputDir . '/' . $className . '.php';
            $code = $transpiler->transpile($jsonPath, $className, 'VISU\\Generated\\Scenes');
            file_put_contents($outputPath, $code);

            // Update registry
            $registry = new \VISU\Transpiler\TranspilerRegistry($this->cacheDir);
            $registry->record($jsonPath, $outputPath);
            $registry->save();

            return ['success' => true, 'output' => $className . '.php'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, output?: string, error?: string}|null
     */
    private function autoTranspileUI(string $jsonPath): ?array
    {
        if ($this->cacheDir === null) {
            return null;
        }

        try {
            $transpiler = new \VISU\Transpiler\UITranspiler();
            $baseName = pathinfo($jsonPath, PATHINFO_FILENAME);
            $className = $this->toClassName($baseName);
            $outputDir = $this->cacheDir . '/transpiled/UI';

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputPath = $outputDir . '/' . $className . '.php';
            $code = $transpiler->transpile($jsonPath, $className, 'VISU\\Generated\\UI');
            file_put_contents($outputPath, $code);

            $registry = new \VISU\Transpiler\TranspilerRegistry($this->cacheDir);
            $registry->record($jsonPath, $outputPath);
            $registry->save();

            return ['success' => true, 'output' => $className . '.php'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Transpile all scenes, UI layouts, and prefabs.
     */
    private function transpileAll(): void
    {
        if ($this->cacheDir === null) {
            echo json_encode(['ok' => false, 'error' => 'Cache directory not configured']);
            return;
        }

        $results = ['scenes' => [], 'ui' => [], 'prefabs' => []];

        // Transpile scenes
        $scenesDir = $this->resourcesDir . '/scenes';
        if (is_dir($scenesDir)) {
            foreach (glob($scenesDir . '/*.json') ?: [] as $file) {
                $name = basename($file, '.json');
                $results['scenes'][$name] = $this->autoTranspileScene($file);
            }
        }

        // Transpile UI layouts
        $uiDir = $this->resourcesDir . '/ui';
        if (is_dir($uiDir)) {
            foreach (glob($uiDir . '/*.json') ?: [] as $file) {
                $name = basename($file, '.json');
                $results['ui'][$name] = $this->autoTranspileUI($file);
            }
        }

        echo json_encode(['ok' => true, 'results' => $results]);
    }

    /**
     * Converts a file basename to a PascalCase class name.
     */
    private function toClassName(string $baseName): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9]+/', ' ', $baseName) ?? $baseName;
        return str_replace(' ', '', ucwords($cleaned));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function worldPath(string $name): string
    {
        return rtrim($this->worldsDir, '/') . '/' . $name . '.world.json';
    }

    private function error(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }
}
