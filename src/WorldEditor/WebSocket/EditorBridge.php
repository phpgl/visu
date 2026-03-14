<?php

namespace VISU\WorldEditor\WebSocket;

/**
 * Bridges the editor WebSocket server with the game engine.
 *
 * Handles message routing between connected editor clients and the game engine,
 * enabling live preview updates, entity selection sync, and scene hot-reloading.
 *
 * Message Types (Editor -> Game):
 *   scene.changed    - A scene/world file was modified
 *   entity.selected  - An entity was selected in the editor
 *   entity.updated   - An entity's properties were changed
 *   camera.moved     - The editor camera position changed
 *   transpile.request - Request transpilation of a file
 *
 * Message Types (Game -> Editor):
 *   game.state       - Game state update (FPS, entity count, etc.)
 *   scene.loaded     - Game loaded a scene
 *   error            - Error notification
 */
class EditorBridge
{
    private WebSocketServer $server;

    /** @var array<string, mixed> Last known game state */
    private array $gameState = [];

    /** @var string|null Path to the change notification file */
    private ?string $changeFilePath;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 8766,
        ?string $changeFilePath = null,
    ) {
        $this->server = new WebSocketServer($host, $port);
        $this->changeFilePath = $changeFilePath;

        $this->registerHandlers();
    }

    public function getServer(): WebSocketServer
    {
        return $this->server;
    }

    /**
     * Run the WebSocket bridge (blocking).
     */
    public function run(): void
    {
        $this->server->run();
    }

    /**
     * Notify all connected clients that a scene/world file changed.
     */
    public function notifySceneChanged(string $name, string $type = 'world'): void
    {
        $this->server->broadcast([
            'type' => 'scene.changed',
            'data' => [
                'name' => $name,
                'fileType' => $type,
                'timestamp' => microtime(true),
            ],
        ]);

        // Also write to change notification file for game engine polling
        $this->writeChangeNotification($name, $type);
    }

    /**
     * Notify that a transpilation completed.
     *
     * @param array<string, mixed> $result
     */
    public function notifyTranspileComplete(string $sourcePath, array $result): void
    {
        $this->server->broadcast([
            'type' => 'transpile.result',
            'data' => [
                'source' => $sourcePath,
                'success' => $result['success'] ?? false,
                'output' => $result['output'] ?? null,
                'error' => $result['error'] ?? null,
                'timestamp' => microtime(true),
            ],
        ]);
    }

    /**
     * Update the shared game state.
     *
     * @param array<string, mixed> $state
     */
    public function updateGameState(array $state): void
    {
        $this->gameState = array_merge($this->gameState, $state);
        $this->server->broadcast([
            'type' => 'game.state',
            'data' => $this->gameState,
        ]);
    }

    private function registerHandlers(): void
    {
        $this->server->on('connect', function (int $clientId, mixed $data): void {
            // Send current game state to newly connected client
            if (!empty($this->gameState)) {
                $this->server->send($clientId, [
                    'type' => 'game.state',
                    'data' => $this->gameState,
                ]);
            }
        });

        $this->server->on('scene.changed', function (int $clientId, mixed $data): void {
            // Re-broadcast to all other clients
            $this->server->broadcast([
                'type' => 'scene.changed',
                'data' => $data,
            ]);
        });

        $this->server->on('entity.selected', function (int $clientId, mixed $data): void {
            $this->server->broadcast([
                'type' => 'entity.selected',
                'data' => $data,
            ]);
        });

        $this->server->on('entity.updated', function (int $clientId, mixed $data): void {
            $this->server->broadcast([
                'type' => 'entity.updated',
                'data' => $data,
            ]);
        });

        $this->server->on('ping', function (int $clientId, mixed $data): void {
            $this->server->send($clientId, [
                'type' => 'pong',
                'data' => ['clients' => $this->server->getClientCount()],
            ]);
        });
    }

    private function writeChangeNotification(string $name, string $type): void
    {
        if ($this->changeFilePath === null) {
            return;
        }

        $dir = dirname($this->changeFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $notification = json_encode([
            'name' => $name,
            'type' => $type,
            'timestamp' => microtime(true),
        ]);

        if ($notification !== false) {
            file_put_contents($this->changeFilePath, $notification);
        }
    }
}
