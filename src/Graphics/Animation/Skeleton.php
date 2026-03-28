<?php

namespace VISU\Graphics\Animation;

class Skeleton
{
    /**
     * @var array<Bone>
     */
    public array $bones = [];

    /**
     * Map bone name to bone index for fast lookup
     * @var array<string, int>
     */
    private array $nameMap = [];

    public function addBone(Bone $bone): void
    {
        $this->bones[$bone->index] = $bone;
        $this->nameMap[$bone->name] = $bone->index;
    }

    public function getBoneByName(string $name): ?Bone
    {
        $index = $this->nameMap[$name] ?? null;
        return $index !== null ? ($this->bones[$index] ?? null) : null;
    }

    public function getBoneIndex(string $name): int
    {
        return $this->nameMap[$name] ?? -1;
    }

    public function boneCount(): int
    {
        return count($this->bones);
    }
}
