<?php

namespace VISU\Save;

class SaveSlot
{
    public function __construct(
        public readonly string $name,
        public readonly int $version,
        public readonly float $timestamp,
        public readonly float $playTime,
        public readonly string $description,
        /** @var array<string, mixed> */
        public readonly array $gameState,
        /** @var array<string, mixed>|null */
        public readonly ?array $sceneData = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'version' => $this->version,
            'timestamp' => $this->timestamp,
            'playTime' => $this->playTime,
            'description' => $this->description,
            'gameState' => $this->gameState,
        ];

        if ($this->sceneData !== null) {
            $data['sceneData'] = $this->sceneData;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            version: (int) ($data['version'] ?? 1),
            timestamp: (float) ($data['timestamp'] ?? 0.0),
            playTime: (float) ($data['playTime'] ?? 0.0),
            description: (string) ($data['description'] ?? ''),
            gameState: (array) ($data['gameState'] ?? []),
            sceneData: isset($data['sceneData']) ? (array) $data['sceneData'] : null,
        );
    }
}
