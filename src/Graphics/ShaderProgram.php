<?php

namespace VISU\Graphics;

use VISU\Graphics\Exception\ShaderProgramException;
use VISU\Graphics\Exception\ShaderProgramLinkingException;

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
     * quickly sets a uniform value using "glUniform1f"
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
}
