<?php

namespace VISU\UI;

use VISU\Locale\LocaleManager;

class UIDataContext
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    private ?LocaleManager $localeManager = null;

    /**
     * Sets a value at a dot-notation path.
     * e.g. set('economy.money', 1500)
     */
    public function set(string $path, mixed $value): void
    {
        $this->data[$path] = $value;
    }

    /**
     * Gets a value at a dot-notation path.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        return $this->data[$path] ?? $default;
    }

    /**
     * Bulk set multiple values.
     *
     * @param array<string, mixed> $values
     */
    public function setAll(array $values): void
    {
        foreach ($values as $path => $value) {
            $this->data[$path] = $value;
        }
    }

    public function getLocaleManager(): ?LocaleManager
    {
        return $this->localeManager;
    }

    public function setLocaleManager(LocaleManager $localeManager): void
    {
        $this->localeManager = $localeManager;
    }

    /**
     * Resolves binding expressions in a string.
     * e.g. "Geld: {economy.money}" -> "Geld: 1500"
     *
     * Translation bindings use {t:key} or {t:key|param=value} syntax
     * and are resolved via the LocaleManager when available.
     */
    public function resolveBindings(string $text): string
    {
        // Resolve translation expressions first
        if ($this->localeManager !== null && str_contains($text, '{t:')) {
            $text = $this->localeManager->resolveTranslations($text);
        }

        return (string) preg_replace_callback('/\{([^}]+)\}/', function (array $matches): string {
            $path = $matches[1];
            $value = $this->get($path);
            if ($value === null) {
                return $matches[0]; // keep original if unresolved
            }
            if (is_float($value)) {
                return number_format($value, 2);
            }
            return (string) $value;
        }, $text);
    }

    /**
     * Resolves a single binding expression to its raw value.
     * Returns the binding string itself if it's not a pure binding.
     */
    public function resolveValue(string $expr): mixed
    {
        if (preg_match('/^\{([^}]+)\}$/', $expr, $matches)) {
            return $this->get($matches[1]);
        }
        return $expr;
    }

    /**
     * Checks if a string contains binding expressions.
     */
    public function hasBindings(string $text): bool
    {
        return (bool) preg_match('/\{[^}]+\}/', $text);
    }

    /**
     * Returns all stored data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
