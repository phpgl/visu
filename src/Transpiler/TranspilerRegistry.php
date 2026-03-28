<?php

namespace VISU\Transpiler;

class TranspilerRegistry
{
    /**
     * @var array<string, array{hash: string, output: string, timestamp: float}>
     */
    private array $entries = [];

    private string $registryPath;

    public function __construct(string $cachePath)
    {
        $this->registryPath = rtrim($cachePath, '/') . '/transpiler_registry.json';
        $this->load();
    }

    /**
     * Check if a source file needs to be re-transpiled.
     */
    public function needsUpdate(string $sourcePath): bool
    {
        if (!isset($this->entries[$sourcePath])) {
            return true;
        }

        $currentHash = $this->hashFile($sourcePath);
        return $currentHash !== $this->entries[$sourcePath]['hash'];
    }

    /**
     * Record that a file has been transpiled.
     */
    public function record(string $sourcePath, string $outputPath): void
    {
        $this->entries[$sourcePath] = [
            'hash' => $this->hashFile($sourcePath),
            'output' => $outputPath,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get the output path for a previously transpiled source.
     */
    public function getOutputPath(string $sourcePath): ?string
    {
        return $this->entries[$sourcePath]['output'] ?? null;
    }

    /**
     * Remove a source entry from the registry.
     */
    public function remove(string $sourcePath): void
    {
        unset($this->entries[$sourcePath]);
    }

    /**
     * Persist registry to disk.
     */
    public function save(): void
    {
        $dir = dirname($this->registryPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            file_put_contents($this->registryPath, $json);
        }
    }

    /**
     * Load registry from disk.
     */
    private function load(): void
    {
        if (!file_exists($this->registryPath)) {
            return;
        }

        $json = file_get_contents($this->registryPath);
        if ($json === false) {
            return;
        }

        $data = json_decode($json, true);
        if (is_array($data)) {
            $this->entries = $data;
        }
    }

    /**
     * @return array<string, array{hash: string, output: string, timestamp: float}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Clear all entries.
     */
    public function clear(): void
    {
        $this->entries = [];
    }

    private function hashFile(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }
        return md5_file($path) ?: '';
    }
}
