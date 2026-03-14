<?php

namespace VISU\Tests\WorldEditor;

use PHPUnit\Framework\TestCase;
use VISU\WorldEditor\WebSocket\EditorBridge;
use VISU\WorldEditor\WebSocket\WebSocketServer;

class WebSocketServerTest extends TestCase
{
    public function testCanBeConstructed(): void
    {
        $server = new WebSocketServer('127.0.0.1', 19876);
        $this->assertInstanceOf(WebSocketServer::class, $server);
    }

    public function testClientCountStartsAtZero(): void
    {
        $server = new WebSocketServer('127.0.0.1', 19877);
        $this->assertSame(0, $server->getClientCount());
    }

    public function testCanRegisterHandlers(): void
    {
        $server = new WebSocketServer('127.0.0.1', 19878);
        $called = false;
        $server->on('test', function () use (&$called) {
            $called = true;
        });
        // Handler registered without error
        $this->assertFalse($called);
    }

    public function testBroadcastWithNoClientsDoesNotError(): void
    {
        $server = new WebSocketServer('127.0.0.1', 19879);
        // Should not throw
        $server->broadcast(['type' => 'test', 'data' => []]);
        $this->assertTrue(true);
    }

    public function testSendToNonExistentClientDoesNotError(): void
    {
        $server = new WebSocketServer('127.0.0.1', 19880);
        $server->send(9999, ['type' => 'test']);
        $this->assertTrue(true);
    }

    public function testEditorBridgeCanBeConstructed(): void
    {
        $bridge = new EditorBridge('127.0.0.1', 19881);
        $this->assertInstanceOf(EditorBridge::class, $bridge);
    }

    public function testEditorBridgeReturnsServer(): void
    {
        $bridge = new EditorBridge('127.0.0.1', 19882);
        $this->assertInstanceOf(WebSocketServer::class, $bridge->getServer());
    }

    public function testEditorBridgeNotifySceneChangedWithNoClients(): void
    {
        $bridge = new EditorBridge('127.0.0.1', 19883);
        // Should not throw even with no clients
        $bridge->notifySceneChanged('test_world', 'world');
        $this->assertTrue(true);
    }

    public function testEditorBridgeNotifyTranspileComplete(): void
    {
        $bridge = new EditorBridge('127.0.0.1', 19884);
        $bridge->notifyTranspileComplete('/path/to/scene.json', ['success' => true, 'output' => 'Scene.php']);
        $this->assertTrue(true);
    }

    public function testEditorBridgeChangeNotificationFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/visu_ws_test_changes_' . uniqid() . '.json';
        $bridge = new EditorBridge('127.0.0.1', 19885, $tmpFile);
        $bridge->notifySceneChanged('my_world', 'world');

        $this->assertFileExists($tmpFile);
        $data = json_decode((string) file_get_contents($tmpFile), true);
        $this->assertIsArray($data);
        $this->assertSame('my_world', $data['name']);
        $this->assertSame('world', $data['type']);

        unlink($tmpFile);
    }
}
