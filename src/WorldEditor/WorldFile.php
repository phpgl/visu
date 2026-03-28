<?php

namespace VISU\WorldEditor;

class WorldFile
{
    public string $version = '1.0';

    /** @var array<string, mixed> */
    public array $meta = [];

    /** @var array<string, mixed> */
    public array $camera = [];

    /** @var array<int, array<string, mixed>> */
    public array $layers = [];

    /** @var array<int, array<string, mixed>> */
    public array $lights = [];

    /** @var array<int, array<string, mixed>> */
    public array $tilesets = [];

    private function __construct() {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $world = new self();
        $world->version  = $data['version'] ?? '1.0';
        $world->meta     = $data['meta'] ?? self::defaultMeta('Untitled');
        $world->camera   = $data['camera'] ?? self::defaultCamera();
        $world->layers   = $data['layers'] ?? [];
        $world->lights   = $data['lights'] ?? [];
        $world->tilesets = $data['tilesets'] ?? [];
        return $world;
    }

    public static function create(string $name = 'Untitled'): self
    {
        $world = new self();
        $world->version  = '1.0';
        $world->meta     = self::defaultMeta($name);
        $world->camera   = self::defaultCamera();
        $world->layers   = [
            [
                'id'      => 'bg',
                'name'    => 'Background',
                'type'    => 'tile',
                'visible' => true,
                'locked'  => false,
                'tiles'   => (object)[],
            ],
            [
                'id'       => 'entities',
                'name'     => 'Entities',
                'type'     => 'entity',
                'visible'  => true,
                'locked'   => false,
                'entities' => [],
            ],
        ];
        $world->lights   = [];
        $world->tilesets = [];
        return $world;
    }

    public static function load(string $path): self
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("World file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read world file: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in world file: {$path}");
        }

        return self::fromArray($data);
    }

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->meta['modified'] = date('c');

        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode world to JSON");
        }

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to write world file: {$path}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'  => $this->version,
            'meta'     => $this->meta,
            'camera'   => $this->camera,
            'layers'   => $this->layers,
            'lights'   => $this->lights,
            'tilesets' => $this->tilesets,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLayers(): array
    {
        return $this->layers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultMeta(string $name): array
    {
        $now = date('c');
        return [
            'name'     => $name,
            'type'     => '2d_topdown',
            'tileSize' => 32,
            'created'  => $now,
            'modified' => $now,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultCamera(): array
    {
        return [
            'position' => ['x' => 0, 'y' => 0],
            'zoom'     => 1.0,
        ];
    }
}
