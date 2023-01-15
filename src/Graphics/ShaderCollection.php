<?php

namespace VISU\Graphics;

use VISU\Graphics\Exception\ShaderException;
use VISU\Graphics\Exception\ShaderProgramLinkingException;

class ShaderCollection
{   
    /**
     * Array of shader programs
     * 
     * @var array<string, ShaderProgram>
     */
    private array $shaderPrograms = [];

    /**
     * Pre registered shader files that can be loaded on demand
     * 
     * @var array<string, array<int, string>>
     */
    private array $avilableShaderFiles = [];

    /**
     * Array of global defines that will be added to all shader programs
     * 
     * @var array<string, int|float|string>
     */
    private array $globalDefines = [];

    /**
     * Shader file loader instance
     */
    private ShaderFileLoader $shaderFileLoader;

    /**
     * Constructor
     * 
     * @param GLState $gl
     * @param string $shaderDirectory The directory where the shader files are located
     */
    public function __construct(
        private GLState $gl,
        private string $shaderDirectory, 
    )
    {
        $this->shaderFileLoader = new ShaderFileLoader($shaderDirectory);
    }

    /**
     * Enables VISU shader includes
     */
    public function enableVISUIncludes(): void
    {
        $this->shaderFileLoader->addIncludePath(VISU_PATH_FRAMEWORK_RESOURCES_SHADER . '/include/');
    }

    /**
     * Sets a global define that will be added to all shader programs
     * 
     * @param string $name
     * @param int|float|string $value
     */
    public function setGlobalDefine(string $name, $value): void
    {
        $this->globalDefines[$name] = $value;
    }

    /**
     * Adds a shader program to the collection
     * 
     * @param string $name A unique name / identifier for the shader program
     * @param ShaderProgram $shaderProgram
     */
    public function setShaderProgram(string $name, ShaderProgram $shaderProgram): void
    {
        if (isset($this->shaderPrograms[$name])) {
            throw new ShaderException("Shader program with name '{$name}' already exists");
        }

        $this->shaderPrograms[$name] = $shaderProgram;
    }

    /**
     * Adds a shader program from an array of shader stage files 
     * 
     * ```php
     * $shaders->registerFromFiles('myshader', [
     *   ShaderStage::VERTEX => 'myshader.vert.glsl',
     *   ShaderStage::FRAGMENT => 'myshader.frag.glsl',
     * ]);
     * 
     * @param string $name 
     * @param array<int, string> $paths An array of shader stage constants as keys and paths to the shader files as values
     * @return void 
     */
    public function registerFromFiles(string $name, array $paths) : void
    {
        if (isset($this->shaderPrograms[$name])) {
            throw new ShaderException("Shader program with name '{$name}' already exists");
        }

        $this->avilableShaderFiles[$name] = $paths;
    }

    /**
     * Returns a shader program from the collection
     * 
     * @param string $name The name of the shader program
     */
    public function get(string $name) : ShaderProgram 
    {
        if (!isset($this->shaderPrograms[$name])) 
        {
            if (!isset($this->avilableShaderFiles[$name])) {
                throw new ShaderException("Shader program with name '{$name}' does not exist and is not registered");
            }

            $shaderProgram = new ShaderProgram($this->gl);

            // attach all shader stages
            foreach($this->avilableShaderFiles[$name] as $stage => $path) {
                $shaderProgram->attach(new ShaderStage($stage, $this->shaderFileLoader->loadShader($path, $this->globalDefines)));
            }
            
            // link the shader program
            try {
                $shaderProgram->link();
            } catch(ShaderException $e) {
                throw new ShaderProgramLinkingException("ShaderException ('{$name}'): " . $e->getMessage(), $e->getCode(), $e);
            }

            // add the shader program to the collection
            $this->setShaderProgram($name, $shaderProgram);
        }

        return $this->shaderPrograms[$name];
    }

    /**
     * Scans the shader directory for shader files and adds them to the collection
     * 
     * @param string|null $directoryPath The directory to scan. If null, the shader directory will be used
     */
    public function scanShaderDirectory(?string $directoryPath = null): void
    {
        $directoryPath = $directoryPath ?: $this->shaderDirectory;
        $directoryPathLen = strlen($directoryPath);

        // recursivly scan the directory for shader files
        $programFiles = []; 

        $directoryIterator = new \RecursiveDirectoryIterator($directoryPath);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);

        foreach($iterator as $file) 
        {
            // skip directories
            if ($file->isDir()) continue;

            $absolutePath = $file->getRealPath();

            if (substr($absolutePath, -10) === '.vert.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::VERTEX] = $absolutePath;
            }
            elseif (substr($absolutePath, -10) === '.frag.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::FRAGMENT] = $absolutePath;
            }
            elseif (substr($absolutePath, -10) === '.geo.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::GEOMETRY] = $absolutePath;
            }
            // elseif (substr($absolutePath, -10) === '.comp.glsl') {
            //     $shaderName = substr($absolutePath, $directoryPathLen + 1, -10);
            //     if (!isset($programFiles[$shaderName])) {
            //         $programFiles[$shaderName] = [];
            //     }
            //     $programFiles[$shaderName][ShaderStage::COMPUTE] = $absolutePath;
            // }
            elseif (substr($absolutePath, -11) === '.tessc.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -11);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::TESS_CONTROL] = $absolutePath;
            }
            elseif (substr($absolutePath, -11) === '.tesse.glsl') {
                $shaderName = substr($absolutePath, $directoryPathLen + 1, -11);
                if (!isset($programFiles[$shaderName])) {
                    $programFiles[$shaderName] = [];
                }
                $programFiles[$shaderName][ShaderStage::TESS_EVALUATION] = $absolutePath;
            }
        }

        // register all found shader files
        foreach($programFiles as $name => $files) {
            $this->registerFromFiles($name, $files);
        }
    }

    /**
     * Specifially scans the VISU shader directory for shader files and adds them to the collection
     */
    public function addVISUShaders() : void
    {
        $this->scanShaderDirectory(VISU_PATH_FRAMEWORK_RESOURCES_SHADER);
    }

    /**
     * Loads all currently registered shader programs, you can pass a callback to be called after each shader program is loaded
     * This way you can show a loading screen or something...
     * 
     * @param callable|null $callback The callback to be called after each shader program is loaded
     */
    public function loadAll(?callable $callback = null) : void
    {
        foreach($this->avilableShaderFiles as $name => $files) {
            $shader = $this->get($name);
            if ($callback) $callback($name, $shader);
        }
    }
}
