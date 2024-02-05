<?php

namespace VISU\Graphics;

use GL\Math\Mat4;
use GL\Math\Vec2;
use GL\Math\Vec3;
use GL\Math\Vec4;
use VISU\Graphics\Exception\ShaderProgramException;
use VISU\Graphics\Exception\ShaderProgramLinkingException;

/**
 * This class is a wrapper for OpenGL shader programs.
 * 
 * You will see some duplicated code in this class.
 * Microoptimizations are bad bla bla, but as some of the methods here 
 * are called many many times during the rendering process, squeezing our 
 * as much overhead as possible is a good idea. Obviously, PHP itself is 
 * a bottleneck here but VISU is PHP library..
 */
class ShaderProgram
{
    /**
     * OpenGL shader ID
     */
    public readonly int $id;

    /**
     * Linked status, to avaid calling glGetProgramiv every time
     * 
     * @var bool
     */
    private bool $isLinked = false;

    /**
     * Vertex stage
     */
    private ?ShaderStage $vertexShader = null;

    /**
     * Fragment stage
     */
    private ?ShaderStage $fragmentShader = null;

    /**
     * Geometry stage
     */
    private ?ShaderStage $geometryShader = null;

    /**
     * Tessellation control stage
     */
    private ?ShaderStage $tessControlShader = null;

    /**
     * Tessellation evaluation stage
     */
    private ?ShaderStage $tessEvaluationShader = null;

    // /**
    //  * Compute stage
    //  */
    // private ?ShaderStage $computeShader = null;

    /**
     * An array of cached uniform locations for this shader program
     * 
     * @var array<string, int>
     */
    private array $uniformLocationMap = [];

    /**
     * Constructor
     * Creating a program object will also create the program in OpenGL
     */
    public function __construct(
        private GLState $glState
    )
    {
        $this->id = glCreateProgram();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        glDeleteProgram($this->id);
    }

    /**
     * Returns boolean indicating whether the program is linked (GL_LINK_STATUS)
     */
    public function isLinked() : bool
    {
        return $this->isLinked;
    }

    /**
     * Attaches a shader stage to the shader program
     * 
     * @param ShaderStage $shader 
     * @return void 
     */
    public function attach(ShaderStage $shader) : void
    {   
        if ($shader->type === ShaderStage::VERTEX) {
            if (!is_null($this->vertexShader)) {
                glDetachShader($this->id, $this->vertexShader->id);
            }

            $this->vertexShader = $shader;
            glAttachShader($this->id, $this->vertexShader->id);
        } elseif ($shader->type === ShaderStage::FRAGMENT) {
            if (!is_null($this->fragmentShader)) {
                glDetachShader($this->id, $this->fragmentShader->id);
            }

            $this->fragmentShader = $shader;
            glAttachShader($this->id, $this->fragmentShader->id);
        } elseif ($shader->type === ShaderStage::GEOMETRY) {
            if (!is_null($this->geometryShader)) {
                glDetachShader($this->id, $this->geometryShader->id);
            }

            $this->geometryShader = $shader;
            glAttachShader($this->id, $this->geometryShader->id);
        } elseif ($shader->type === ShaderStage::TESS_CONTROL) {
            if (!is_null($this->tessControlShader)) {
                glDetachShader($this->id, $this->tessControlShader->id);
            }

            $this->tessControlShader = $shader;
            glAttachShader($this->id, $this->tessControlShader->id);
        } elseif ($shader->type === ShaderStage::TESS_EVALUATION) {
            if (!is_null($this->tessEvaluationShader)) {
                glDetachShader($this->id, $this->tessEvaluationShader->id);
            }

            $this->tessEvaluationShader = $shader;
            glAttachShader($this->id, $this->tessEvaluationShader->id);
        } 
        // elseif ($shader->type === ShaderStage::COMPUTE) {
        //     if (!is_null($this->computeShader)) {
        //         glDetachShader($this->id, $this->computeShader->id);
        //     }

        //     $this->computeShader = $shader;
        // } 
        else {
            throw new ShaderProgramException(sprintf("Unknown shader type, cannot attach shader of type: %d", $shader->type));
        }

    }

    /**
     * Will check if the given stage needs to be compiled and if so, will compile it
     * and afterwards atatch it to the program
     */
    private function compileShaderStage(?ShaderStage $shader) : void
    {
        // no shader nothing todo
        if (is_null($shader)) return;

        // compile the shader if 
        if (!$shader->isCompiled()) {
            $shader->compile();
        }
    }

    /**
     * Returns the shader log length (GL_INFO_LOG_LENGTH)
     */
    public function getLogLength() : int
    {
        glGetProgramiv($this->id, GL_INFO_LOG_LENGTH, $length);
        return $length;
    }

    /**
     * Returns the shader info log (GL_INFO_LOG)
     */
    public function getInfoLog() : string
    {
        return glGetProgramInfoLog($this->id, $this->getLogLength());
    }

    /**
     * Compiles all stages (if requrired) and links the program.
     * 
     * @return void 
     */
    public function link() : void
    {
        $this->compileShaderStage($this->vertexShader);
        $this->compileShaderStage($this->fragmentShader);
        $this->compileShaderStage($this->geometryShader);
        $this->compileShaderStage($this->tessControlShader);
        $this->compileShaderStage($this->tessEvaluationShader);
        // $this->compileShaderStage($this->computeShader);

        glLinkProgram($this->id);

        // check for sucess
        glGetProgramiv($this->id, GL_LINK_STATUS, $linkSuccess);
        
        if ($linkSuccess == GL_FALSE) {
            throw new ShaderProgramLinkingException(sprintf("Failed to link program: %s", $this->getInfoLog()));
        }

        $this->isLinked = true;
    }

    /**
     * Sets this shader program as the current program in the OpenGL context
     * This method will check if the program is linked and if it is alreay set as the current program.
     * 
     * @throws ShaderProgramException if the program is not linked
     */
    public function use() : void
    {   
        if (!$this->isLinked) {
            throw new ShaderProgramException("Cannot use program that is not linked");
        }

        // only use if not already in use
        if ($this->glState->currentProgram !== $this->id) {
            glUseProgram($this->id);
            $this->glState->currentProgram = $this->id;
        }
    }

    /**
     * Returns the uniform location of the given uniform name
     * 
     * @param string $name 
     * @return int 
     */
    public function getUniformLocation(string $name) : int
    {
        if (!isset($this->uniformLocationMap[$name])) {
            $this->uniformLocationMap[$name] = glGetUniformLocation($this->id, $name);
        }

        return $this->uniformLocationMap[$name];
    }

    /**
     * --------------------------------------------------------------------------------
     * Unsafe uniform setters
     * --------------------------------------------------------------------------------
     */

    /**
     * Sets a uniform value using `glUniform1f`, this methods is faster 
     * than using `setUniform1f()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform1f` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param float $value
     * @return void 
     */
    public function unsafeSetUniform1f(string $name, float $value) : void
    {
        glUniform1f($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform1i`, this methods is faster 
     * than using `setUniform1i()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform1i` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value
     * @return void 
     */
    public function unsafeSetUniform1i(string $name, int $value) : void
    {
        glUniform1i($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform1fv`, this methods is faster 
     * than using `setUniform1fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform1fv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniform1fv(string $name, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniform1fv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform1iv`, this methods is faster 
     * than using `setUniform1iv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform1iv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\IntBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform1iv(string $name, \GL\Buffer\IntBuffer|array $value) : void
    {
        glUniform1iv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform2f`, this methods is faster 
     * than using `setUniform2f()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform2f` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param float $value1
     * @param float $value2
     * @return void 
     */
    public function unsafeSetUniform2f(string $name, float $value1, float $value2) : void
    {
        glUniform2f($this->uniformLocationMap[$name], $value1, $value2);
    }

    /**
     * Sets a uniform value using `glUniform2i`, this methods is faster 
     * than using `setUniform2i()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform2i` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value1
     * @param int $value2
     * @return void 
     */
    public function unsafeSetUniform2i(string $name, int $value1, int $value2) : void
    {
        glUniform2i($this->uniformLocationMap[$name], $value1, $value2);
    }

    /**
     * Sets a uniform value using `glUniform2fv`, this methods is faster 
     * than using `setUniform2fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform2fv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniform2fv(string $name, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniform2fv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform2iv`, this methods is faster 
     * than using `setUniform2iv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform2iv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\IntBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform2iv(string $name, \GL\Buffer\IntBuffer|array $value) : void
    {
        glUniform2iv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform3f`, this methods is faster 
     * than using `setUniform3f()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform3f` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param float $value1
     * @param float $value2
     * @param float $value3
     * @return void 
     */
    public function unsafeSetUniform3f(string $name, float $value1, float $value2, float $value3) : void
    {
        glUniform3f($this->uniformLocationMap[$name], $value1, $value2, $value3);
    }

    /**
     * Sets a uniform value using `glUniform3i`, this methods is faster 
     * than using `setUniform3i()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform3i` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value1
     * @param int $value2
     * @param int $value3
     * @return void 
     */
    public function unsafeSetUniform3i(string $name, int $value1, int $value2, int $value3) : void
    {
        glUniform3i($this->uniformLocationMap[$name], $value1, $value2, $value3);
    }

    /**
     * Sets a uniform value using `glUniform3fv`, this methods is faster 
     * than using `setUniform3fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform3fv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniform3fv(string $name, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniform3fv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform3iv`, this methods is faster 
     * than using `setUniform3iv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform3iv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\IntBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform3iv(string $name, \GL\Buffer\IntBuffer|array $value) : void
    {
        glUniform3iv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform4f`, this methods is faster 
     * than using `setUniform4f()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform4f` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param float $value1
     * @param float $value2
     * @param float $value3
     * @param float $value4
     * @return void 
     */
    public function unsafeSetUniform4f(string $name, float $value1, float $value2, float $value3, float $value4) : void
    {
        glUniform4f($this->uniformLocationMap[$name], $value1, $value2, $value3, $value4);
    }

    /**
     * Sets a uniform value using `glUniform4i`, this methods is faster 
     * than using `setUniform4i()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform4i` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value1
     * @param int $value2
     * @param int $value3
     * @param int $value4
     * @return void 
     */
    public function unsafeSetUniform4i(string $name, int $value1, int $value2, int $value3, int $value4) : void
    {
        glUniform4i($this->uniformLocationMap[$name], $value1, $value2, $value3, $value4);
    }

    /**
     * Sets a uniform value using `glUniform4fv`, this methods is faster 
     * than using `setUniform4fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform4fv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniform4fv(string $name, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniform4fv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform4iv`, this methods is faster 
     * than using `setUniform4iv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform4iv` directly. 
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\IntBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform4iv(string $name, \GL\Buffer\IntBuffer|array $value) : void
    {
        glUniform4iv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform1ui`, this methods is faster
     * than using `setUniform1ui()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform1ui` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value
     * @return void 
     */
    public function unsafeSetUniform1ui(string $name, int $value) : void
    {
        glUniform1ui($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform2ui`, this methods is faster
     * than using `setUniform2ui()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform2ui` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value1
     * @param int $value2
     * @return void 
     */
    public function unsafeSetUniform2ui(string $name, int $value1, int $value2) : void
    {
        glUniform2ui($this->uniformLocationMap[$name], $value1, $value2);
    }

    /**
     * Sets a uniform value using `glUniform3ui`, this methods is faster
     * than using `setUniform3ui()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform3ui` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value1
     * @param int $value2
     * @param int $value3
     * @return void 
     */
    public function unsafeSetUniform3ui(string $name, int $value1, int $value2, int $value3) : void
    {
        glUniform3ui($this->uniformLocationMap[$name], $value1, $value2, $value3);
    }

    /**
     * Sets a uniform value using `glUniform4ui`, this methods is faster
     * than using `setUniform4ui()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform4ui` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param int $value1
     * @param int $value2
     * @param int $value3
     * @param int $value4
     * @return void 
     */
    public function unsafeSetUniform4ui(string $name, int $value1, int $value2, int $value3, int $value4) : void
    {
        glUniform4ui($this->uniformLocationMap[$name], $value1, $value2, $value3, $value4);
    }

    /**
     * Sets a uniform value using `glUniform1uiv`, this methods is faster
     * than using `setUniform1uiv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform1uiv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\UintBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform1uiv(string $name, \GL\Buffer\UintBuffer|array $value) : void
    {
        glUniform1uiv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform2uiv`, this methods is faster
     * than using `setUniform2uiv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform2uiv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\UintBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform2uiv(string $name, \GL\Buffer\UintBuffer|array $value) : void
    {
        glUniform2uiv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform3uiv`, this methods is faster
     * than using `setUniform3uiv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform3uiv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\UintBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform3uiv(string $name, \GL\Buffer\UintBuffer|array $value) : void
    {
        glUniform3uiv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniform4uiv`, this methods is faster
     * than using `setUniform4uiv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniform4uiv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\UintBuffer|array<int> $value
     * @return void 
     */
    public function unsafeSetUniform4uiv(string $name, \GL\Buffer\UintBuffer|array $value) : void
    {
        glUniform4uiv($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix2fv`, this methods is faster
     * than using `setUniformMatrix2fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix2fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix2fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix2fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix3fv`, this methods is faster
     * than using `setUniformMatrix3fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix3fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix3fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix3fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix4fv`, this methods is faster
     * than using `setUniformMatrix4fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix4fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix4fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix4fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix2x3fv`, this methods is faster
     * than using `setUniformMatrix2x3fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix2x3fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix2x3fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix2x3fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix3x2fv`, this methods is faster
     * than using `setUniformMatrix3x2fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix3x2fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix3x2fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix3x2fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix2x4fv`, this methods is faster
     * than using `setUniformMatrix2x4fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix2x4fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix2x4fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix2x4fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix4x2fv`, this methods is faster
     * than using `setUniformMatrix4x2fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix4x2fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix4x2fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix4x2fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix3x4fv`, this methods is faster
     * than using `setUniformMatrix3x4fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix3x4fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix3x4fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix3x4fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix4x3fv`, this methods is faster
     * than using `setUniformMatrix4x3fv()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix4x3fv` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param \GL\Buffer\FloatBuffer|array<float> $value
     * @return void 
     */
    public function unsafeSetUniformMatrix4x3fv(string $name, bool $transpose, \GL\Buffer\FloatBuffer|array $value) : void
    {
        glUniformMatrix4x3fv($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * Sets a uniform value using `glUniformVec2f`, this methods is faster
     * than using `setUniformVec2f()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformVec2f` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param Vec2 $value 
     * @return void 
     */
    public function unsafeSetUniformVec2(string $name, Vec2 $value) : void
    {
        glUniformVec2f($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniformVec3f`, this methods is faster
     * than using `setUniformVec3f()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformVec3f` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param Vec3 $value 
     * @return void 
     */
    public function unsafeSetUniformVec3(string $name, Vec3 $value) : void
    {
        glUniformVec3f($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniformVec4f`, this methods is faster
     * than using `setUniformVec4f()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformVec4f` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param Vec4 $value 
     * @return void 
     */
    public function unsafeSetUniformVec4(string $name, Vec4 $value) : void
    {
        glUniformVec4f($this->uniformLocationMap[$name], $value);
    }

    /**
     * Sets a uniform value using `glUniformMatrix4f`, this methods is faster
     * than using `setUniformMat4()` because it skips a bunch of checks, this is why it is labeled unsafe.
     * 
     * If you want to eliminate more overhead store the uniform location on your own and call `glUniformMatrix4f` directly.
     * If this turns out to still be the bottleneck you a different technique then uniforms like SSBOs
     * 
     * !!! This methods expects that you already bound the program using "use"
     * !!! This methods also expects that the uniform name your are using has been binded beforehand.
     * 
     * @param string $name The uniforms name
     * @param bool $transpose Whether to transpose the matrix
     * @param Mat4 $value
     * @return void 
     */
    public function unsafeSetUniformMat4(string $name, bool $transpose, Mat4 $value) : void
    {
        glUniformMatrix4f($this->uniformLocationMap[$name], $transpose, $value);
    }

    /**
     * --------------------------------------------------------------------------------
     * Safe uniform setters (more overhead)
     * --------------------------------------------------------------------------------
     */
    
    /**
     * Sets a uniform value using `glUniform1i`
     * 
     * @param string $name The uniforms name
     * @param int $value 
     * @return void 
     */
    public function setUniform1i(string $name, int $value) : void
    {
        $this->use();
        glUniform1i($this->getUniformLocation($name), $value);
    }

    /**
     * Sets a `int` uniform value in the shader program. 
     * This is identical to the method `setUniform1i`
     * 
     * @param string $name The uniforms name
     * @param int $value 
     * @return void 
     */
    public function setUniformInt(string $name, int $value) : void
    {
        $this->setUniform1i($name, $value);
    }

    /**
     * Sets a uniform value using `glUniform1f`
     * 
     * @param string $name The uniforms name
     * @param float $value 
     * @return void 
     */
    public function setUniform1f(string $name, float $value) : void
    {
        $this->use();
        glUniform1f($this->getUniformLocation($name), $value);
    }

    /**
     * Sets a `float` uniform value in the shader program. 
     * This is identical to the method `setUniform1f`
     * 
     * @param string $name The uniforms name
     * @param float $value 
     * @return void 
     */
    public function setUniformFloat(string $name, float $value) : void
    {
        $this->setUniform1f($name, $value);
    }

    /**
     * Sets a uniform value using `glUniform2i`
     * 
     * @param string $name The uniforms name
     * @param int $value1 
     * @param int $value2 
     * @return void 
     */
    public function setUniform2i(string $name, int $value1, int $value2) : void
    {
        $this->use();
        glUniform2i($this->getUniformLocation($name), $value1, $value2);
    }

    /**
     * Sets a `ivec2` uniform value in the shader program. 
     * This is identical to the method `setUniform2i`
     * 
     * @param string $name The uniforms name
     * @param int $value1 
     * @param int $value2 
     * @return void 
     */
    public function setUniformIvec2(string $name, int $value1, int $value2) : void
    {
        $this->setUniform2i($name, $value1, $value2);
    }

    /**
     * Sets a uniform value using `glUniform2f`
     * 
     * @param string $name The uniforms name
     * @param float $value1 
     * @param float $value2 
     * @return void 
     */
    public function setUniform2f(string $name, float $value1, float $value2) : void
    {
        $this->use();
        glUniform2f($this->getUniformLocation($name), $value1, $value2);
    }

    /**
     * Sets a `vec2` uniform value in the shader program. 
     * This is identical to the method `setUniform2f`
     * 
     * @param string $name The uniforms nam
     * @param Vec2 $vec The vector object to set as unfiform value
     * @return void 
     */
    public function setUniformVec2(string $name, Vec2 $vec) : void
    {
        $this->use();
        glUniformVec2f($this->getUniformLocation($name), $vec);
    }

    /**
     * Sets a uniform value using `glUniform3i`
     * 
     * @param string $name The uniforms name
     * @param int $value1 
     * @param int $value2 
     * @param int $value3 
     * @return void 
     */
    public function setUniform3i(string $name, int $value1, int $value2, int $value3) : void
    {
        $this->use();
        glUniform3i($this->getUniformLocation($name), $value1, $value2, $value3);
    }

    /**
     * Sets a `ivec3` uniform value in the shader program. 
     * This is identical to the method `setUniform3i`
     * 
     * @param string $name The uniforms name
     * @param int $value1 
     * @param int $value2 
     * @param int $value3 
     * @return void 
     */
    public function setUniformIvec3(string $name, int $value1, int $value2, int $value3) : void
    {
        $this->setUniform3i($name, $value1, $value2, $value3);
    }

    /**
     * Sets a uniform value using `glUniform3f`
     * 
     * @param string $name The uniforms name
     * @param float $value1 
     * @param float $value2 
     * @param float $value3 
     * @return void 
     */
    public function setUniform3f(string $name, float $value1, float $value2, float $value3) : void
    {
        $this->use();
        glUniform3f($this->getUniformLocation($name), $value1, $value2, $value3);
    }

    /**
     * Sets a `vec3` uniform value in the shader program. 
     * This is identical to the method `setUniform3f`
     * 
     * @param string $name The uniforms name
     * @param Vec3 $vec The vector object to set as unfiform value
     * @return void 
     */
    public function setUniformVec3(string $name, Vec3 $vec) : void
    {
        $this->use();
        glUniformVec3f($this->getUniformLocation($name), $vec);
    }

    /**
     * Sets a uniform value using `glUniform4i`
     * 
     * @param string $name The uniforms name
     * @param int $value1 
     * @param int $value2 
     * @param int $value3 
     * @param int $value4 
     * @return void 
     */
    public function setUniform4i(string $name, int $value1, int $value2, int $value3, int $value4) : void
    {
        $this->use();
        glUniform4i($this->getUniformLocation($name), $value1, $value2, $value3, $value4);
    }

    /**
     * Sets a `ivec4` uniform value in the shader program. 
     * This is identical to the method `setUniform4i`
     * 
     * @param string $name The uniforms name
     * @param int $value1 
     * @param int $value2 
     * @param int $value3 
     * @param int $value4 
     * @return void 
     */
    public function setUniformIvec4(string $name, int $value1, int $value2, int $value3, int $value4) : void
    {
        $this->setUniform4i($name, $value1, $value2, $value3, $value4);
    }

    /**
     * Sets a uniform value using `glUniform4f`
     * 
     * @param string $name The uniforms name
     * @param float $value1 
     * @param float $value2 
     * @param float $value3 
     * @param float $value4 
     * @return void 
     */
    public function setUniform4f(string $name, float $value1, float $value2, float $value3, float $value4) : void
    {
        $this->use();
        glUniform4f($this->getUniformLocation($name), $value1, $value2, $value3, $value4);
    }

    /**
     * Sets a `vec4` uniform value in the shader program. 
     * This is identical to the method `setUniform4f`
     * 
     * @param string $name The uniforms name
     * @param Vec4 $vec The vector object to set as unfiform value
     * @return void 
     */
    public function setUniformVec4(string $name, Vec4 $vec) : void
    {
        $this->use();
        glUniformVec4f($this->getUniformLocation($name), $vec);
    }

    /**
     * Sets a `mat4` uniform value in the shader program. 
     * 
     * @param string $name The uniforms name
     * @param bool $transpose 
     * @param Mat4 $mat The matrix object to set as unfiform value
     * @return void 
     */
    public function setUniformMatrix4f(string $name, bool $transpose, Mat4 $mat) : void
    {
        $this->use();
        glUniformMatrix4f($this->getUniformLocation($name), $transpose, $mat);
    }

    /**
     * Sets a `vec4` uniform value in the shader program. 
     * This is identical to the method `setUniformMatrix4f`
     * 
     * @param string $name The uniforms name
     * @param bool $transpose
     * @param Mat4 $mat The matrix object to set as unfiform value
     * @return void 
     */
    public function setUniformMat4(string $name, bool $transpose, Mat4 $mat) : void
    {
        $this->use();
        glUniformMatrix4f($this->getUniformLocation($name), $transpose, $mat);
    }

    /**
     * --------------------------------------------------------------------------------
     * Safe uniform array setters (more overhead)
     * --------------------------------------------------------------------------------
     */

    /**
     * Sets an array of uniform float values using `glUniform1fv`
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $values The array of values to set
     * @return void 
     */
    public function setUniformFloatArray(string $name, \GL\Buffer\FloatBuffer|array $values) :  void
    {
        $this->use();
        glUniform1fv($this->getUniformLocation($name), $values);
    }

    /**
     * Sets an array of uniform int values using `glUniform1iv`
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\IntBuffer|array<int> $values The array of values to set
     * @return void 
     */
    public function setUniformIntArray(string $name, \GL\Buffer\IntBuffer|array $values) :  void
    {
        $this->use();
        glUniform1iv($this->getUniformLocation($name), $values);
    }

    /**
     * Sets an array of uniform unsigned int values using `glUniform1uiv`
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\UintBuffer|array<int> $values The array of values to set
     * @return void 
     */
    public function setUniformUintArray(string $name, \GL\Buffer\UintBuffer|array $values) :  void
    {
        $this->use();
        glUniform1uiv($this->getUniformLocation($name), $values);
    }

    /**
     * Sets an array of uniform `vec2` values using `glUniform2fv`
     * 
     * Values are passed as flat array:
     *  - [x1, y1, x2, y2, x3, y3, x4, y4]
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $values The array of values to set
     * @return void 
     */
    public function setUniformVec2Array(string $name, \GL\Buffer\FloatBuffer|array $values) :  void
    {
        $this->use();
        glUniform2fv($this->getUniformLocation($name), $values);
    }

    /**
     * Sets an array of uniform `vec3` values using `glUniform3fv`
     * 
     * Values are passed as flat array:
     *  - [x1, y1, z1, x2, y2, z2, x3, y3, z3, x4, y4, z4]
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $values The array of values to set
     * @return void 
     */
    public function setUniformVec3Array(string $name, \GL\Buffer\FloatBuffer|array $values) :  void
    {
        $this->use();
        glUniform3fv($this->getUniformLocation($name), $values);
    }

    /**
     * Sets an array of uniform `vec4` values using `glUniform4fv`
     * 
     * Values are passed as flat array:
     *  - [x1, y1, z1, w1, x2, y2, z2, w2, x3, y3, z3, w3, x4, y4, z4, w4]
     * 
     * @param string $name The uniforms name
     * @param \GL\Buffer\FloatBuffer|array<float> $values The array of values to set
     * @return void 
     */
    public function setUniformVec4Array(string $name, \GL\Buffer\FloatBuffer|array $values) :  void
    {
        $this->use();
        glUniform4fv($this->getUniformLocation($name), $values);
    }

    /**
     * Sets an array of uniforms using their key as the location and guessed type of the value
     * 
     * Example:
     *     $program->setUniformsKV([
     *          "u_color" => new Vec4(1.0, 0.0, 0.0, 1.0),
     *          "u_matrix" => new Mat4(),
     *          "u_time" => 1.0
     *          "u_resolution" => new Vec2(800, 600)
     *      ]);
     *  
     * 
     * @param array<string, mixed> $uniforms The uniforms to set
     * @return void 
     */
    public function setUniformsKV(array $uniforms) : void
    {
        $this->use();

        foreach($uniforms as $name => $value)
        {
            $location = $this->getUniformLocation($name);

            if(is_int($value))
            {
                glUniform1i($location, $value);
            }
            else if(is_float($value))
            {
                glUniform1f($location, $value);
            }
            else if($value instanceof Vec2)
            {
                glUniformVec2f($location, $value);
            }
            else if($value instanceof Vec3)
            {
                glUniformVec3f($location, $value);
            }
            else if($value instanceof Vec4)
            {
                glUniformVec4f($location, $value);
            }
            else if($value instanceof Mat4)
            {
                glUniformMatrix4f($location, false, $value);
            }
            else {
                throw new \InvalidArgumentException("Invalid uniform value type for uniform: $name");
            }
        }
    }
}
