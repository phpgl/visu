<?php

namespace VISU\Build;

class BuildConfig
{
    public string $name = 'Game';
    public string $identifier = 'com.visu.game';
    public string $version = '1.0.0';
    public string $entry = 'game.php';

    /** PHP code to execute after bootstrap (e.g. "\\App\\Game::run($container);") */
    public string $run = '';

    /** @var array<string> */
    public array $phpExtensions = ['glfw', 'mbstring'];

    /** @var array<string> */
    public array $phpExtraLibs = ['-lc++'];

    /** @var array<string> Glob patterns to exclude from PHAR */
    public array $pharExclude = ['**/tests', '**/Tests', '**/test', '**/docs', '**/doc', '**/editor', '**/.git', '**/.idea', '**/.phpunit*', '**/examples'];

    /** @var array<string> Additional PHP files to require in stub */
    public array $additionalRequires = [];

    /** @var array<string> Resource dirs that stay external (not in PHAR) */
    public array $externalResources = [];

    /** @var array<string, array<string, mixed>> Platform-specific config */
    public array $platforms = [];

    public string $projectRoot;

    private function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    /**
     * Load config from build.json with fallbacks from composer.json
     */
    public static function load(string $projectRoot): self
    {
        $config = new self($projectRoot);

        // Read defaults from composer.json
        $composerFile = $projectRoot . '/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode((string) file_get_contents($composerFile), true);
            if (is_array($composer)) {
                if (isset($composer['name'])) {
                    $parts = explode('/', $composer['name']);
                    $config->name = ucfirst(end($parts));
                }
                if (isset($composer['version'])) {
                    $config->version = $composer['version'];
                }
            }
        }

        // Override with build.json if present
        $buildFile = $projectRoot . '/build.json';
        if (file_exists($buildFile)) {
            $build = json_decode((string) file_get_contents($buildFile), true);
            if (is_array($build)) {
                $config->applyBuildJson($build);
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyBuildJson(array $data): void
    {
        if (isset($data['name'])) $this->name = $data['name'];
        if (isset($data['identifier'])) $this->identifier = $data['identifier'];
        if (isset($data['version'])) $this->version = $data['version'];
        if (isset($data['entry'])) $this->entry = $data['entry'];
        if (isset($data['run'])) $this->run = $data['run'];

        if (isset($data['php']['extensions'])) $this->phpExtensions = $data['php']['extensions'];
        if (isset($data['php']['extraLibs'])) $this->phpExtraLibs = $data['php']['extraLibs'];

        if (isset($data['phar']['exclude'])) $this->pharExclude = $data['phar']['exclude'];
        if (isset($data['phar']['additionalRequires'])) $this->additionalRequires = $data['phar']['additionalRequires'];

        if (isset($data['resources']['external'])) $this->externalResources = $data['resources']['external'];
        if (isset($data['platforms'])) $this->platforms = $data['platforms'];
    }

    /**
     * Dump config summary for dry-run output
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'identifier' => $this->identifier,
            'version' => $this->version,
            'entry' => $this->entry,
            'php.extensions' => $this->phpExtensions,
            'php.extraLibs' => $this->phpExtraLibs,
            'phar.exclude' => $this->pharExclude,
            'phar.additionalRequires' => $this->additionalRequires,
            'resources.external' => $this->externalResources,
            'platforms' => $this->platforms,
        ];
    }
}
