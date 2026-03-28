<?php

namespace VISU\AI;

use VISU\ECS\EntitiesInterface;

class BTContext
{
    /**
     * Blackboard for sharing data between nodes
     * @var array<string, mixed>
     */
    public array $blackboard = [];

    public function __construct(
        public readonly int $entity,
        public readonly EntitiesInterface $entities,
        public readonly float $deltaTime,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->blackboard[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->blackboard[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->blackboard);
    }
}
