<?php

namespace VISU\WorldEditor\WebSocket;

/**
 * Lightweight WebSocket server for the VISU World Editor.
 *
 * Handles the WebSocket handshake, frame encoding/decoding, and message routing.
 * Runs as a standalone PHP process alongside the HTTP editor server.
 *
 * Protocol: JSON messages with { "type": "...", "data": ... } structure.
 */
class WebSocketServer
{
    /** @var resource|null */
    private $serverSocket = null;

    /** @var array<int, resource> Connected client sockets */
    private array $clients = [];

    /** @var array<string, array<callable>> */
    private array $handlers = [];

    private bool $running = false;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 8766,
    ) {
    }

    /**
     * Register a handler for a specific message type.
     */
    public function on(string $messageType, callable $handler): void
    {
        $this->handlers[$messageType][] = $handler;
    }

    /**
     * Start the WebSocket server (blocking).
     */
    public function run(): void
    {
        $socket = stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        );

        if ($socket === false) {
            throw new \RuntimeException("Failed to start WebSocket server: [{$errno}] {$errstr}");
        }

        $this->serverSocket = $socket;

        stream_set_blocking($this->serverSocket, false);
        $this->running = true;

        $this->log("WebSocket server listening on ws://{$this->host}:{$this->port}");

        while ($this->running) {
            $this->tick();
            usleep(10000); // 10ms poll interval
        }

        $this->shutdown();
    }

    /**
     * Perform one tick of the event loop.
     */
    public function tick(): void
    {
        if ($this->serverSocket === null) {
            return;
        }

        // Check for new connections
        $newClient = @stream_socket_accept($this->serverSocket, 0);
        if ($newClient !== false) {
            $this->handleNewConnection($newClient);
        }

        // Check for data from existing clients
        foreach ($this->clients as $id => $client) {
            $data = @fread($client, 65536);
            if ($data === false || $data === '') {
                if (feof($client)) {
                    $this->disconnectClient($id);
                }
                continue;
            }
            $this->handleClientData($id, $data);
        }
    }

    /**
     * Send a JSON message to all connected clients.
     *
     * @param array<string, mixed> $message
     */
    public function broadcast(array $message): void
    {
        $json = json_encode($message);
        if ($json === false) {
            return;
        }

        $frame = $this->encodeFrame($json);
        foreach ($this->clients as $id => $client) {
            $written = @fwrite($client, $frame);
            if ($written === false) {
                $this->disconnectClient($id);
            }
        }
    }

    /**
     * Send a message to a specific client.
     *
     * @param array<string, mixed> $message
     */
    public function send(int $clientId, array $message): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        $json = json_encode($message);
        if ($json === false) {
            return;
        }

        $frame = $this->encodeFrame($json);
        @fwrite($this->clients[$clientId], $frame);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function getClientCount(): int
    {
        return count($this->clients);
    }

    // ── Connection handling ──────────────────────────────────────────────

    /**
     * @param resource $socket
     */
    private function handleNewConnection($socket): void
    {
        stream_set_blocking($socket, false);

        // Read the HTTP upgrade request
        $headers = '';
        $attempts = 0;
        while ($attempts < 100) {
            $line = @fread($socket, 4096);
            if ($line !== false && $line !== '') {
                $headers .= $line;
                if (str_contains($headers, "\r\n\r\n")) {
                    break;
                }
            }
            $attempts++;
            usleep(1000);
        }

        if (!str_contains($headers, 'Upgrade: websocket') && !str_contains($headers, 'Upgrade: WebSocket')) {
            // Not a WebSocket request — close connection
            fclose($socket);
            return;
        }

        // Extract Sec-WebSocket-Key
        if (!preg_match('/Sec-WebSocket-Key:\s*(.+?)\r\n/i', $headers, $m)) {
            fclose($socket);
            return;
        }

        $key = trim($m[1]);
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-5AB5DF11BE85', true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n"
            . "\r\n";

        fwrite($socket, $response);

        $id = (int) $socket;
        $this->clients[$id] = $socket;
        $this->log("Client connected (id: {$id}, total: " . count($this->clients) . ')');

        $this->dispatch('connect', $id, []);
    }

    private function handleClientData(int $id, string $data): void
    {
        $decoded = $this->decodeFrame($data);
        if ($decoded === null) {
            return;
        }

        // Handle close frame
        if ($decoded['opcode'] === 0x08) {
            $this->disconnectClient($id);
            return;
        }

        // Handle ping
        if ($decoded['opcode'] === 0x09) {
            $pong = $this->encodeFrame($decoded['payload'], 0x0A);
            @fwrite($this->clients[$id], $pong);
            return;
        }

        // Text frame
        if ($decoded['opcode'] === 0x01) {
            $message = json_decode($decoded['payload'], true);
            if (is_array($message) && isset($message['type'])) {
                $this->dispatch((string) $message['type'], $id, $message['data'] ?? []);
            }
        }
    }

    private function disconnectClient(int $id): void
    {
        if (isset($this->clients[$id])) {
            @fclose($this->clients[$id]);
            unset($this->clients[$id]);
            $this->log("Client disconnected (id: {$id}, remaining: " . count($this->clients) . ')');
            $this->dispatch('disconnect', $id, []);
        }
    }

    /**
     * @param array<mixed>|mixed $data
     */
    private function dispatch(string $type, int $clientId, mixed $data): void
    {
        foreach ($this->handlers[$type] ?? [] as $handler) {
            try {
                $handler($clientId, $data);
            } catch (\Throwable $e) {
                $this->log("Handler error [{$type}]: {$e->getMessage()}");
            }
        }
    }

    // ── WebSocket frame encoding/decoding ─────────────────────────────

    /**
     * Decode a WebSocket frame from the client (masked).
     *
     * @return array{opcode: int, payload: string}|null
     */
    private function decodeFrame(string $data): ?array
    {
        $len = strlen($data);
        if ($len < 2) {
            return null;
        }

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) !== 0;
        $payloadLength = $secondByte & 0x7F;
        $offset = 2;

        if ($payloadLength === 126) {
            if ($len < 4) {
                return null;
            }
            $payloadLength = unpack('n', substr($data, 2, 2));
            if ($payloadLength === false) {
                return null;
            }
            $payloadLength = $payloadLength[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            if ($len < 10) {
                return null;
            }
            $payloadLength = unpack('J', substr($data, 2, 8));
            if ($payloadLength === false) {
                return null;
            }
            $payloadLength = $payloadLength[1];
            $offset = 10;
        }

        $mask = '';
        if ($masked) {
            if ($len < $offset + 4) {
                return null;
            }
            $mask = substr($data, $offset, 4);
            $offset += 4;
        }

        if ($len < $offset + $payloadLength) {
            return null;
        }

        $payload = substr($data, $offset, (int) $payloadLength);

        if ($masked && $mask !== '') {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
            }
        }

        return ['opcode' => $opcode, 'payload' => $payload];
    }

    /**
     * Encode a WebSocket frame (server -> client, unmasked).
     */
    private function encodeFrame(string $payload, int $opcode = 0x01): string
    {
        $frame = chr(0x80 | $opcode);
        $len = strlen($payload);

        if ($len <= 125) {
            $frame .= chr($len);
        } elseif ($len <= 65535) {
            $frame .= chr(126) . pack('n', $len);
        } else {
            $frame .= chr(127) . pack('J', $len);
        }

        return $frame . $payload;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function shutdown(): void
    {
        foreach ($this->clients as $id => $client) {
            @fclose($client);
        }
        $this->clients = [];

        if ($this->serverSocket !== null) {
            fclose($this->serverSocket);
            $this->serverSocket = null;
        }

        $this->log('WebSocket server stopped.');
    }

    private function log(string $message): void
    {
        $time = date('H:i:s');
        fwrite(STDERR, "[{$time}] [WS] {$message}\n");
    }
}
