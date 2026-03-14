<?php

namespace VISU\Graphics;

use VISU\Graphics\Exception\GLValidationException;

/**
 * GL error validation utility.
 *
 * Drains the OpenGL error queue and optionally throws on errors.
 * Used by RenderPipeline in debug mode and by tests.
 */
class GLValidator
{
    /**
     * @var array<array{error: int, hex: string, context: string}>
     */
    private array $collectedErrors = [];

    /**
     * Maps GL error codes to human-readable names
     */
    private static function errorName(int $error): string
    {
        return match ($error) {
            GL_INVALID_ENUM => 'GL_INVALID_ENUM',
            GL_INVALID_VALUE => 'GL_INVALID_VALUE',
            GL_INVALID_OPERATION => 'GL_INVALID_OPERATION',
            GL_INVALID_FRAMEBUFFER_OPERATION => 'GL_INVALID_FRAMEBUFFER_OPERATION',
            GL_OUT_OF_MEMORY => 'GL_OUT_OF_MEMORY',
            default => 'GL_UNKNOWN_ERROR',
        };
    }

    /**
     * Drains all pending GL errors and returns them.
     *
     * @return array<array{error: int, name: string, hex: string}>
     */
    public static function drainErrors(): array
    {
        $errors = [];
        while (($e = glGetError()) !== GL_NO_ERROR) {
            $errors[] = [
                'error' => $e,
                'name' => self::errorName($e),
                'hex' => '0x' . dechex($e),
            ];
        }
        return $errors;
    }

    /**
     * Checks for GL errors and throws if any are found.
     *
     * @throws GLValidationException
     */
    public static function check(string $context = ''): void
    {
        $errors = self::drainErrors();
        if (!empty($errors)) {
            $names = array_map(fn($e) => $e['name'] . ' (' . $e['hex'] . ')', $errors);
            $msg = implode(', ', $names);
            throw new GLValidationException(
                $context ? "{$context}: {$msg}" : $msg
            );
        }
    }

    /**
     * Collects GL errors without throwing, storing them for later inspection.
     */
    public function collect(string $context = ''): void
    {
        $errors = self::drainErrors();
        foreach ($errors as $error) {
            $this->collectedErrors[] = [
                'error' => $error['error'],
                'hex' => $error['hex'],
                'context' => $context . ': ' . $error['name'],
            ];
        }
    }

    /**
     * Returns all collected errors.
     *
     * @return array<array{error: int, hex: string, context: string}>
     */
    public function getCollectedErrors(): array
    {
        return $this->collectedErrors;
    }

    /**
     * Returns true if errors have been collected.
     */
    public function hasErrors(): bool
    {
        return !empty($this->collectedErrors);
    }

    /**
     * Clears collected errors.
     */
    public function clear(): void
    {
        $this->collectedErrors = [];
    }

    /**
     * Formats all collected errors as a readable string.
     */
    public function formatErrors(): string
    {
        if (empty($this->collectedErrors)) {
            return 'No GL errors';
        }

        $lines = [];
        foreach ($this->collectedErrors as $err) {
            $lines[] = "  [{$err['hex']}] {$err['context']}";
        }
        return "GL errors:\n" . implode("\n", $lines);
    }
}
