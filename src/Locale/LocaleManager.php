<?php

namespace VISU\Locale;

use VISU\Signal\DispatcherInterface;
use VISU\Signals\Locale\LocaleChangedSignal;

class LocaleManager
{
    private string $currentLocale = 'en';

    private string $fallbackLocale = 'en';

    /**
     * Loaded translations keyed by locale.
     * Each locale maps flat dot-notation keys to translated strings.
     *
     * @var array<string, array<string, string>>
     */
    private array $translations = [];

    private ?DispatcherInterface $dispatcher;

    public function __construct(?DispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    public function setFallbackLocale(string $locale): void
    {
        $this->fallbackLocale = $locale;
    }

    /**
     * Switches the active locale and dispatches a signal.
     */
    public function setLocale(string $locale): void
    {
        $previous = $this->currentLocale;
        $this->currentLocale = $locale;

        if ($previous !== $locale && $this->dispatcher !== null) {
            $this->dispatcher->dispatch(
                'locale.changed',
                new LocaleChangedSignal($previous, $locale)
            );
        }
    }

    /**
     * Loads translations from a JSON file.
     * The file may contain nested objects which are flattened to dot-notation keys.
     *
     * Example: {"menu": {"start": "Start Game"}} becomes "menu.start" => "Start Game"
     */
    public function loadFile(string $locale, string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Translation file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read translation file: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in translation file: {$path}");
        }

        $this->loadArray($locale, $data);
    }

    /**
     * Loads translations from a nested array.
     *
     * @param array<string, mixed> $data
     */
    public function loadArray(string $locale, array $data): void
    {
        if (!isset($this->translations[$locale])) {
            $this->translations[$locale] = [];
        }

        $this->flattenInto($data, '', $this->translations[$locale]);
    }

    /**
     * Loads all JSON files from a directory. Each file's basename (without .json)
     * is used as the locale identifier.
     *
     * Example: loadDirectory('resources/locale/') loads en.json as "en", de.json as "de"
     */
    public function loadDirectory(string $directory): void
    {
        $directory = rtrim($directory, '/');
        if (!is_dir($directory)) {
            throw new \RuntimeException("Translation directory not found: {$directory}");
        }

        $files = glob($directory . '/*.json');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $locale = basename($file, '.json');
            $this->loadFile($locale, $file);
        }
    }

    /**
     * Translates a key with optional parameter substitution.
     *
     * Parameters in the translation string use :name syntax:
     *   "items.count" => "You have :count items"
     *   get('items.count', ['count' => 5]) => "You have 5 items"
     *
     * @param array<string, string|int|float> $params
     */
    public function get(string $key, array $params = []): string
    {
        $text = $this->translations[$this->currentLocale][$key]
            ?? $this->translations[$this->fallbackLocale][$key]
            ?? $key;

        if ($params !== []) {
            $text = $this->interpolateParams($text, $params);
        }

        return $text;
    }

    /**
     * Translates a key with pluralization.
     *
     * Translation strings use pipe-separated forms: "singular|plural"
     * For languages with more forms: "zero|one|few|many|other"
     *
     * Supports simple two-form (count == 1 ? first : second) and
     * explicit count forms like {0}, {1}, [2,5].
     *
     * @param array<string, string|int|float> $params
     */
    public function choice(string $key, int $count, array $params = []): string
    {
        $raw = $this->translations[$this->currentLocale][$key]
            ?? $this->translations[$this->fallbackLocale][$key]
            ?? $key;

        $text = $this->selectPluralForm($raw, $count);

        $params['count'] = $count;

        return $this->interpolateParams($text, $params);
    }

    /**
     * Checks whether a translation key exists for the current or fallback locale.
     */
    public function has(string $key): bool
    {
        return isset($this->translations[$this->currentLocale][$key])
            || isset($this->translations[$this->fallbackLocale][$key]);
    }

    /**
     * Returns all available locales (those that have loaded translations).
     *
     * @return array<int, string>
     */
    public function getAvailableLocales(): array
    {
        return array_keys($this->translations);
    }

    /**
     * Resolves translation expressions in a string.
     * Expressions use the format {t:key} or {t:key|param=value|param2=value2}
     */
    public function resolveTranslations(string $text): string
    {
        return (string) preg_replace_callback('/\{t:([^}]+)\}/', function (array $matches): string {
            $expr = $matches[1];
            $parts = explode('|', $expr);
            $key = $parts[0];

            $params = [];
            for ($i = 1, $len = count($parts); $i < $len; $i++) {
                $eqPos = strpos($parts[$i], '=');
                if ($eqPos !== false) {
                    $paramName = substr($parts[$i], 0, $eqPos);
                    $paramValue = substr($parts[$i], $eqPos + 1);
                    $params[$paramName] = $paramValue;
                }
            }

            return $this->get($key, $params);
        }, $text);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $target
     */
    private function flattenInto(array $data, string $prefix, array &$target): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $this->flattenInto($value, $fullKey, $target);
            } else {
                $target[$fullKey] = (string) $value;
            }
        }
    }

    /**
     * @param array<string, string|int|float> $params
     */
    private function interpolateParams(string $text, array $params): string
    {
        foreach ($params as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    private function selectPluralForm(string $raw, int $count): string
    {
        $forms = explode('|', $raw);

        if (count($forms) === 1) {
            return $forms[0];
        }

        // Check for explicit count matches: {0} None|{1} One|[2,*] Many
        foreach ($forms as $form) {
            $form = trim($form);
            // Exact match: {N}
            if (preg_match('/^\{(\d+)\}\s*(.+)$/', $form, $m)) {
                if ((int) $m[1] === $count) {
                    return $m[2];
                }
                continue;
            }
            // Range match: [min,max] or [min,*]
            if (preg_match('/^\[(\d+),\s*(\d+|\*)\]\s*(.+)$/', $form, $m)) {
                $min = (int) $m[1];
                $max = $m[2] === '*' ? PHP_INT_MAX : (int) $m[2];
                if ($count >= $min && $count <= $max) {
                    return $m[3];
                }
                continue;
            }
        }

        // Simple two-form: singular|plural
        if (count($forms) === 2) {
            return $count === 1 ? trim($forms[0]) : trim($forms[1]);
        }

        // Three forms: zero|one|many
        if (count($forms) === 3) {
            if ($count === 0) {
                return trim($forms[0]);
            }
            return $count === 1 ? trim($forms[1]) : trim($forms[2]);
        }

        // Fallback to last form
        return trim(end($forms));
    }
}
