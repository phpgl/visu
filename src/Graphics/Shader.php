<?php 

namespace VISU\Graphics;

use VISU\Graphics\Exception\ShaderCompileException;

class Shader 
{
    /*
     * Shader types
     */
    const VERTEX = GL_VERTEX_SHADER;
    const FRAGMENT = GL_FRAGMENT_SHADER;
    const GEOMETRY = GL_GEOMETRY_SHADER;
    const TESS_CONTROL = GL_TESS_CONTROL_SHADER;
    const TESS_EVALUATION = GL_TESS_EVALUATION_SHADER;
    // const COMPUTE = GL_COMPUTE_SHADER;

    /**
     * OpenGL shader ID
     */
    private readonly int $id;

    /**
     * Constructor
     * Creating a shader object will also create the shader in OpenGL
     */
    public function __construct(int $type, ?string $sourceCode = null)
    {
        $this->id = glCreateShader($type);

        if ($sourceCode !== null) {
            $this->setSourceCode($sourceCode);
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        glDeleteShader($this->id);
    }

    /**
     * Sets the shader source code and uploads it to OpenGL
     * 
     * @param string $sourceCode The shader source code
     */
    public function setSourceCode(string $sourceCode) : void
    {
        glShaderSource($this->id, $sourceCode);
    }

    /**
     * Returns boolean indicating whether the shader is compiled (GL_COMPILE_STATUS)
     */
    public function isCompiled() : bool
    {
        glGetShaderiv($this->id, GL_COMPILE_STATUS, $success);
        return $success === GL_TRUE;
    }

    /**
     * Returns boolean indicating whether the shader is marked as deleted (GL_DELETE_STATUS)
     */
    public function isDeleted() : bool
    {
        glGetShaderiv($this->id, GL_DELETE_STATUS, $deleted);
        return $deleted === GL_TRUE;
    }
    
    /**
     * Returns the current shader type (GL_SHADER_TYPE)
     */
    public function getType() : int
    {
        glGetShaderiv($this->id, GL_SHADER_TYPE, $type);
        return $type;
    }

    /**
     * Returns the shader log length (GL_INFO_LOG_LENGTH)
     */
    public function getLogLength() : int
    {
        glGetShaderiv($this->id, GL_INFO_LOG_LENGTH, $length);
        return $length;
    }

    /**
     * Returns the shader source code length (GL_SHADER_SOURCE_LENGTH)
     */
    public function getSourceLength() : int
    {
        glGetShaderiv($this->id, GL_SHADER_SOURCE_LENGTH, $length);
        return $length;
    }

    /**
     * Returns the shader info log (GL_INFO_LOG)
     */
    public function getInfoLog() : string
    {
        return glGetShaderInfoLog($this->id, $this->getLogLength());
    }

    /**
     * Returns a parameter of the shader object
     * 
     * @param int $pname The paramter name (GL_SHADER_TYPE, GL_DELETE_STATUS, GL_COMPILE_STATUS, GL_INFO_LOG_LENGTH, GL_SHADER_SOURCE_LENGTH)
     * @return int 
     */
    public function getShaderiv(int $pname) : int
    {
        glGetShaderiv($this->id, $pname, $value);
        return $value;
    }

    /**
     * Compiles the shader object
     * 
     * @throws ShaderCompileException If the shader compilation fails
     */
    public function compile() : void
    {
        glCompileShader($this->id);
        
        if (!$this->isCompiled()) {
            throw new ShaderCompileException("Shader compilation failed: " . $this->getInfoLog());
        }
    }
}