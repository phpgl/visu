<?php

namespace VISU\Transpiler;

class TranspileContext
{
    /** @var array<string> */
    private array $lines = [];

    /** @var array<string, true> */
    private array $useStatements = [];

    private int $entityIndex = 0;
    private int $componentIndex = 0;

    public function addLine(string $line): void
    {
        $this->lines[] = $line;
    }

    public function requireUse(string $fqcn): void
    {
        $this->useStatements[$fqcn] = true;
    }

    public function nextEntityIndex(): int
    {
        return $this->entityIndex++;
    }

    public function nextComponentIndex(): int
    {
        return $this->componentIndex++;
    }

    /**
     * @return array<string>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @return array<string>
     */
    public function getUseStatements(): array
    {
        return array_keys($this->useStatements);
    }
}
