<?php 

namespace VISU\Graphics;

use GL\Buffer\FloatBuffer;
use GL\Math\Mat4;
use GL\Math\Vec3;
use GL\Texture\Texture2D;
use VISU\Graphics\GLState;
use VISU\Graphics\Cubemap;
use VISU\Graphics\Framebuffer;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;
use VISU\Graphics\CubeVertexArray;
use VISU\Exception\VISUException;

class HDRIToCubemap
{
    private ShaderProgram $equirectangularToCubemapShader;
    private CubeVertexArray $cubeVAO;
    private Framebuffer $framebuffer;

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl
    )
    {
        $this->createShaders();
        $this->createGeometry();
        $this->createFramebuffer();
    }

    /**
     * Creates the shader program for converting equirectangular to cubemap
     */
    private function createShaders(): void
    {
        $this->equirectangularToCubemapShader = new ShaderProgram($this->gl);

        // vertex shader - renders a full screen cube
        $this->equirectangularToCubemapShader->attach(new ShaderStage(ShaderStage::VERTEX, <<<'GLSL'
#version 330 core
layout (location = 0) in vec3 a_pos;

out vec3 local_pos;

uniform mat4 projection;
uniform mat4 view;

void main()
{
    local_pos = a_pos;
    
    gl_Position = projection * view * vec4(local_pos, 1.0);
}
GLSL
        ));

        // fragment shader - converts equirectangular to cubemap
        $this->equirectangularToCubemapShader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<<'GLSL'
#version 330 core
out vec3 frag_color;
in vec3 local_pos;

uniform sampler2D hdritex;

// https://stackoverflow.com/questions/48494389/how-does-this-code-sample-from-a-spherical-map
const vec2 invAtan = vec2(0.1591, 0.3183);
vec2 sampleSphere(vec3 v)
{
    vec2 uv = vec2(atan(v.z, v.x), asin(v.y));
    uv *= invAtan;
    uv += 0.5;
    return uv;
}

void main()
{		
    vec2 uv = sampleSphere(normalize(local_pos));
    vec3 color = texture(hdritex, uv).rgb;
    
    // clamp extremely bright values to prevent numerical issues
    color = min(color, vec3(65504.0)); // max value for 16-bit float
    
    frag_color = color;
}
GLSL
        ));

        $this->equirectangularToCubemapShader->link('equirectangular_to_cubemap');
    }

    /**
     * Creates the cube geometry for rendering
     */
    private function createGeometry(): void
    {
        $this->cubeVAO = new CubeVertexArray($this->gl);
    }

    /**
     * Creates the framebuffer for rendering to cubemap faces
     */
    private function createFramebuffer(): void
    {
        $this->framebuffer = new Framebuffer($this->gl);
    }

    /**
     * Converts an equirectangular HDRI image to a cubemap
     * 
     * @param string $hdriPath Path to the HDRI file
     * @param int $cubemapSize Size of each cubemap face edge
     */
    public function convert(string $hdriPath, int $cubemapSize = 512): Cubemap
    {
        if (!file_exists($hdriPath)) {
            throw new VISUException("HDRI file not found: {$hdriPath}");
        }

        // load the equirectangular HDR texture
        $hdriTexture = $this->loadHDRITexture($hdriPath);

        // create the output cubemap
        $cubemap = new Cubemap($this->gl, "hdri_converted_cubemap");
        
        // allocate cubemap with HDR format
        $options = new TextureOptions();
        $options->internalFormat = GL_RGB16F;
        $options->dataFormat = GL_RGB;
        $options->dataType = GL_FLOAT;
        $options->generateMipmaps = true;
        $options->minFilter = GL_LINEAR_MIPMAP_LINEAR;
        $options->magFilter = GL_LINEAR;
        
        $cubemap->allocateEmpty($cubemapSize, $options);

        // setup framebuffer for rendering to cubemap faces
        $this->framebuffer->bind();
        
        // setup viewport and projection matrix for cubemap faces
        glViewport(0, 0, $cubemapSize, $cubemapSize);
        
        $captureProjection = new Mat4();
        $captureProjection->perspective(\GL\Math\GLM::radians(90.0), 1.0, 0.1, 10.0);
        
        // view matrices for each cubemap face
        $captureViews = [];
        
        // +X face
        $view = new Mat4();
        $view->lookAt(
            new Vec3(0.0, 0.0, 0.0), 
            new Vec3( 1.0,  0.0,  0.0), 
            new Vec3(0.0, -1.0,  0.0)
        );
        $captureViews[] = $view;
        
        // -X face
        $view = new Mat4();
        $view->lookAt(
            new Vec3(0.0, 0.0, 0.0), 
            new Vec3(-1.0,  0.0,  0.0), 
            new Vec3(0.0, -1.0,  0.0)
        );
        $captureViews[] = $view;
        
        // +Y face
        $view = new Mat4();
        $view->lookAt(
            new Vec3(0.0, 0.0, 0.0), 
            new Vec3( 0.0,  1.0,  0.0), 
            new Vec3(0.0,  0.0,  1.0)
        );
        $captureViews[] = $view;
        
        // -Y face
        $view = new Mat4();
        $view->lookAt(
            new Vec3(0.0, 0.0, 0.0), 
            new Vec3( 0.0, -1.0,  0.0), 
            new Vec3(0.0,  0.0, -1.0)
        );
        $captureViews[] = $view;
        
        // +Z face
        $view = new Mat4();
        $view->lookAt(
            new Vec3(0.0, 0.0, 0.0), 
            new Vec3( 0.0,  0.0,  1.0), 
            new Vec3(0.0, -1.0,  0.0)
        );
        $captureViews[] = $view;
        
        // -Z face
        $view = new Mat4();
        $view->lookAt(
            new Vec3(0.0, 0.0, 0.0), 
            new Vec3( 0.0,  0.0, -1.0), 
            new Vec3(0.0, -1.0,  0.0)
        );
        $captureViews[] = $view;

        $this->equirectangularToCubemapShader->use();
        $this->equirectangularToCubemapShader->setUniformMat4('projection', false, $captureProjection);
        
        $hdriTexture->bind(GL_TEXTURE0);
        $this->equirectangularToCubemapShader->setUniformInt('hdritex', 0);

        // render to each cubemap face
        for ($i = 0; $i < 6; $i++) {
            $this->equirectangularToCubemapShader->setUniformMat4('view', false, $captureViews[$i]);
            
            // attach current face to framebuffer
            $this->framebuffer->attachTextureId(GL_COLOR_ATTACHMENT0, Cubemap::FACE_TARGETS[$i], $cubemap->id);
            
            glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
            
            // render cube
            $this->cubeVAO->draw();
        }

        // generate mipmaps for the cubemap
        $cubemap->bind();
        glGenerateMipmap(GL_TEXTURE_CUBE_MAP);

        // unbind framebuffer to restore default state
        glBindFramebuffer(GL_FRAMEBUFFER, 0);

        // reset GL state
        $this->gl->reset();

        return $cubemap;
    }

    /**
     * loads an HDRI texture from a file
     */
    private function loadHDRITexture(string $path): Texture
    {
        $hdriTexture2D = Texture2D::fromDisk($path);
        
        $texture = new Texture($this->gl, 'hdri_equirectangular');
        
        $options = new TextureOptions();
        $options->width = $hdriTexture2D->width();
        $options->height = $hdriTexture2D->height();
        $options->internalFormat = GL_RGB16F;
        $options->dataFormat = GL_RGB;
        $options->dataType = GL_FLOAT;
        $options->generateMipmaps = false;
        $options->minFilter = GL_LINEAR;
        $options->magFilter = GL_LINEAR;
        $options->wrapS = GL_CLAMP_TO_EDGE;
        $options->wrapT = GL_CLAMP_TO_EDGE;
        
        $texture->uploadBuffer($options, $hdriTexture2D->buffer());
        
        return $texture;
    }
}