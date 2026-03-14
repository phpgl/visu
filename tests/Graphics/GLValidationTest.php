<?php

namespace VISU\Tests\Graphics;

use VISU\Graphics\GLState;
use VISU\Graphics\GLValidator;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\Tests\GLContextTestCase;

/**
 * OpenGL-level validation tests.
 *
 * These tests boot an offscreen GL context and exercise real OpenGL calls
 * to catch driver-level errors (GL_INVALID_OPERATION, incomplete FBOs, etc.)
 * that pure PHP unit tests cannot detect.
 *
 * @group glfwinit
 */
class GLValidationTest extends GLContextTestCase
{
    private GLState $gl;

    public function setUp(): void
    {
        parent::setUp();
        $this->createWindow();
        $this->gl = self::$glstate;

        // reset GL state and GLState cache to prevent cross-test pollution
        glUseProgram(0);
        $this->gl->currentProgram = 0;
        glBindVertexArray(0);
        $this->gl->currentVertexArray = 0;
        glBindBuffer(GL_ARRAY_BUFFER, 0);
        $this->gl->currentVertexArrayBuffer = 0;
        glBindBuffer(GL_ELEMENT_ARRAY_BUFFER, 0);
        $this->gl->currentIndexBuffer = 0;
        glBindFramebuffer(GL_FRAMEBUFFER, 0);

        GLValidator::drainErrors();
    }

    public function testNoErrorsOnCleanState(): void
    {
        $errors = GLValidator::drainErrors();
        $this->assertEmpty($errors, 'Expected no GL errors on a clean context');
    }

    public function testShaderCompilationAndLinking(): void
    {
        $shaders = new ShaderCollection($this->gl, VISU_PATH_FRAMEWORK_RESOURCES_SHADER);
        $shaders->enableVISUIncludes();
        $shaders->addVISUShaders();

        $failedShaders = [];
        $shaders->loadAll(function (string $name) use (&$failedShaders) {
            $errors = GLValidator::drainErrors();
            if (!empty($errors)) {
                $names = array_map(fn($e) => $e['name'] . ' (' . $e['hex'] . ')', $errors);
                $failedShaders[$name] = implode(', ', $names);
            }
        });

        $this->assertEmpty(
            $failedShaders,
            'GL errors during shader compilation: ' . print_r($failedShaders, true)
        );
    }

    public function testFullscreenQuadDrawNoErrors(): void
    {
        $this->doFullscreenQuadDraw();
        $this->assertTrue(true);
    }

    private function doFullscreenQuadDraw(): void
    {
        $shader = new ShaderProgram($this->gl);
        $shader->attach(new ShaderStage(ShaderStage::VERTEX, <<<'GLSL'
#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec2 a_uv;
out vec2 v_uv;
void main() {
    v_uv = a_uv;
    gl_Position = vec4(a_position, 1.0);
}
GLSL));
        $shader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<<'GLSL'
#version 330 core
in vec2 v_uv;
out vec4 fragment_color;
void main() {
    fragment_color = vec4(v_uv, 0.0, 1.0);
}
GLSL));
        $shader->link();

        $quad = new QuadVertexArray($this->gl);

        GLValidator::drainErrors();
        $shader->use();
        $quad->draw();
        GLValidator::check('Fullscreen quad draw');

        // $shader and $quad destructors run here, freeing GL resources
    }

    public function testFramebufferCreationAndRendering(): void
    {
        $fbo = 0;
        glGenFramebuffers(1, $fbo);
        glBindFramebuffer(GL_FRAMEBUFFER, $fbo);

        $colorTex = 0;
        glGenTextures(1, $colorTex);
        glBindTexture(GL_TEXTURE_2D, $colorTex);
        glTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA8, 64, 64, 0, GL_RGBA, GL_UNSIGNED_BYTE, null);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_LINEAR);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_LINEAR);
        glFramebufferTexture2D(GL_FRAMEBUFFER, GL_COLOR_ATTACHMENT0, GL_TEXTURE_2D, $colorTex, 0);

        $depthTex = 0;
        glGenTextures(1, $depthTex);
        glBindTexture(GL_TEXTURE_2D, $depthTex);
        glTexImage2D(GL_TEXTURE_2D, 0, GL_DEPTH_COMPONENT24, 64, 64, 0, GL_DEPTH_COMPONENT, GL_FLOAT, null);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
        glFramebufferTexture2D(GL_FRAMEBUFFER, GL_DEPTH_ATTACHMENT, GL_TEXTURE_2D, $depthTex, 0);

        glDrawBuffers(1, GL_COLOR_ATTACHMENT0);

        $status = glCheckFramebufferStatus(GL_FRAMEBUFFER);
        $this->assertEquals(GL_FRAMEBUFFER_COMPLETE, $status, 'Framebuffer not complete, status: 0x' . dechex($status));

        GLValidator::check('FBO creation');

        glViewport(0, 0, 64, 64);
        glClearColor(1.0, 0.0, 0.0, 1.0);
        glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);

        GLValidator::check('FBO clear');

        glBindFramebuffer(GL_FRAMEBUFFER, 0);
        glDeleteFramebuffers(1, $fbo);
        glDeleteTextures(1, $colorTex);
        glDeleteTextures(1, $depthTex);
    }

    public function testMultipleColorAttachments(): void
    {
        $fbo = 0;
        glGenFramebuffers(1, $fbo);
        glBindFramebuffer(GL_FRAMEBUFFER, $fbo);

        $textures = [];
        $attachments = [];
        for ($i = 0; $i < 4; $i++) {
            $tex = 0;
            glGenTextures(1, $tex);
            glBindTexture(GL_TEXTURE_2D, $tex);
            glTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA16F, 64, 64, 0, GL_RGBA, GL_FLOAT, null);
            glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
            glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
            glFramebufferTexture2D(GL_FRAMEBUFFER, GL_COLOR_ATTACHMENT0 + $i, GL_TEXTURE_2D, $tex, 0);
            $textures[] = $tex;
            $attachments[] = GL_COLOR_ATTACHMENT0 + $i;
        }

        glDrawBuffers(count($attachments), ...$attachments);

        $status = glCheckFramebufferStatus(GL_FRAMEBUFFER);
        $this->assertEquals(GL_FRAMEBUFFER_COMPLETE, $status, 'Multi-attachment FBO not complete, status: 0x' . dechex($status));

        GLValidator::check('Multi-attachment FBO');

        glBindFramebuffer(GL_FRAMEBUFFER, 0);
        glDeleteFramebuffers(1, $fbo);
        foreach ($textures as $tex) {
            glDeleteTextures(1, $tex);
        }
    }

    /**
     * Tests the dummy texture pattern used in PBRDeferredLightPass.
     * Verifies that binding 1x1 dummy textures to unused sampler slots
     * prevents GL_INVALID_OPERATION on macOS.
     */
    public function testDummyTextureFixesSamplerValidation(): void
    {
        $shader = new ShaderProgram($this->gl);
        $shader->attach(new ShaderStage(ShaderStage::VERTEX, <<<'GLSL'
#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec2 a_uv;
out vec2 v_uv;
void main() {
    v_uv = a_uv;
    gl_Position = vec4(a_position, 1.0);
}
GLSL));
        $shader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<<'GLSL'
#version 330 core
in vec2 v_uv;
out vec4 fragment_color;
uniform sampler2D tex_used;
uniform sampler2D tex_shadow_0;
uniform sampler2D tex_shadow_1;
uniform sampler2D tex_shadow_2;
uniform sampler2D tex_shadow_3;
uniform int num_shadows;
void main() {
    vec4 color = texture(tex_used, v_uv);
    float shadow = 1.0;
    if (num_shadows > 0) shadow *= texture(tex_shadow_0, v_uv).r;
    if (num_shadows > 1) shadow *= texture(tex_shadow_1, v_uv).r;
    if (num_shadows > 2) shadow *= texture(tex_shadow_2, v_uv).r;
    if (num_shadows > 3) shadow *= texture(tex_shadow_3, v_uv).r;
    fragment_color = color * shadow;
}
GLSL));
        $shader->link();

        // create main texture on unit 0
        $mainTex = 0;
        glGenTextures(1, $mainTex);
        glActiveTexture(GL_TEXTURE0);
        glBindTexture(GL_TEXTURE_2D, $mainTex);
        glTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA8, 1, 1, 0, GL_RGBA, GL_UNSIGNED_BYTE, null);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_NEAREST);

        // create a 1x1 dummy texture for unused sampler slots
        $dummyTex = 0;
        glGenTextures(1, $dummyTex);

        // bind dummy to all shadow sampler slots (units 1-4)
        for ($i = 0; $i < 4; $i++) {
            $unit = 1 + $i;
            glActiveTexture(GL_TEXTURE0 + $unit);
            glBindTexture(GL_TEXTURE_2D, $dummyTex);
            glTexImage2D(GL_TEXTURE_2D, 0, GL_R8, 1, 1, 0, GL_RED, GL_UNSIGNED_BYTE, null);
            glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
            glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
        }

        $shader->setUniform1i('tex_used', 0);
        for ($i = 0; $i < 4; $i++) {
            $shader->setUniform1i("tex_shadow_{$i}", 1 + $i);
        }
        $shader->setUniform1i('num_shadows', 0);

        // create quad and drain any errors from setup
        $quad = new QuadVertexArray($this->gl);
        GLValidator::drainErrors();

        $shader->use();
        GLValidator::drainErrors();

        $quad->draw();

        $errors = GLValidator::drainErrors();
        $this->assertEmpty(
            $errors,
            'GL errors during draw with dummy textures: ' . json_encode($errors)
        );

        glDeleteTextures(1, $mainTex);
        glDeleteTextures(1, $dummyTex);
    }

    /**
     * Tests cubemap texture creation and binding (used for point light shadows).
     */
    public function testCubemapTextureCreationAndBinding(): void
    {
        $cubemap = 0;
        glGenTextures(1, $cubemap);
        glBindTexture(GL_TEXTURE_CUBE_MAP, $cubemap);

        for ($face = 0; $face < 6; $face++) {
            glTexImage2D(
                GL_TEXTURE_CUBE_MAP_POSITIVE_X + $face,
                0, GL_DEPTH_COMPONENT, 64, 64, 0,
                GL_DEPTH_COMPONENT, GL_FLOAT, null
            );
        }

        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_S, GL_CLAMP_TO_EDGE);
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_T, GL_CLAMP_TO_EDGE);
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_R, GL_CLAMP_TO_EDGE);

        GLValidator::check('Cubemap depth texture creation');

        $fbo = 0;
        glGenFramebuffers(1, $fbo);
        glBindFramebuffer(GL_FRAMEBUFFER, $fbo);
        glFramebufferTexture2D(GL_FRAMEBUFFER, GL_DEPTH_ATTACHMENT, GL_TEXTURE_CUBE_MAP_POSITIVE_X, $cubemap, 0);
        glDrawBuffers(1, GL_NONE);
        glReadBuffer(GL_NONE);

        $status = glCheckFramebufferStatus(GL_FRAMEBUFFER);
        $this->assertEquals(GL_FRAMEBUFFER_COMPLETE, $status, 'Cubemap FBO not complete, status: 0x' . dechex($status));

        GLValidator::check('Cubemap FBO');

        glBindFramebuffer(GL_FRAMEBUFFER, 0);
        glDeleteFramebuffers(1, $fbo);
        glDeleteTextures(1, $cubemap);
    }

    /**
     * Tests that a cubemap sampler with a dummy texture doesn't cause errors on draw.
     */
    public function testDummyCubemapSamplerValidation(): void
    {
        $shader = new ShaderProgram($this->gl);
        $shader->attach(new ShaderStage(ShaderStage::VERTEX, <<<'GLSL'
#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec2 a_uv;
out vec2 v_uv;
void main() {
    v_uv = a_uv;
    gl_Position = vec4(a_position, 1.0);
}
GLSL));
        $shader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<<'GLSL'
#version 330 core
in vec2 v_uv;
out vec4 fragment_color;
uniform samplerCube point_shadow_map_0;
uniform samplerCube point_shadow_map_1;
uniform int num_point_shadows;
void main() {
    fragment_color = vec4(v_uv, 0.0, 1.0);
    if (num_point_shadows > 0) {
        fragment_color.r *= texture(point_shadow_map_0, vec3(v_uv, 0.0)).r;
    }
    if (num_point_shadows > 1) {
        fragment_color.g *= texture(point_shadow_map_1, vec3(v_uv, 0.0)).r;
    }
}
GLSL));
        $shader->link();

        // create dummy 1x1 cubemaps
        $cubemapIds = [];
        for ($s = 0; $s < 2; $s++) {
            $cubemap = 0;
            glGenTextures(1, $cubemap);
            glActiveTexture(GL_TEXTURE0 + $s);
            glBindTexture(GL_TEXTURE_CUBE_MAP, $cubemap);

            for ($face = 0; $face < 6; $face++) {
                glTexImage2D(
                    GL_TEXTURE_CUBE_MAP_POSITIVE_X + $face,
                    0, GL_R8, 1, 1, 0,
                    GL_RED, GL_UNSIGNED_BYTE, null
                );
            }

            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_S, GL_CLAMP_TO_EDGE);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_T, GL_CLAMP_TO_EDGE);
            glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_R, GL_CLAMP_TO_EDGE);

            $cubemapIds[] = $cubemap;
        }

        $shader->setUniform1i('point_shadow_map_0', 0);
        $shader->setUniform1i('point_shadow_map_1', 1);
        $shader->setUniform1i('num_point_shadows', 0);

        // draw into own FBO (use texture unit 2 to avoid clobbering cubemap bindings)
        $fbo = 0;
        glGenFramebuffers(1, $fbo);
        glBindFramebuffer(GL_FRAMEBUFFER, $fbo);
        $colorTex = 0;
        glGenTextures(1, $colorTex);
        glActiveTexture(GL_TEXTURE0 + 2);
        glBindTexture(GL_TEXTURE_2D, $colorTex);
        glTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA8, 64, 64, 0, GL_RGBA, GL_UNSIGNED_BYTE, null);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_LINEAR);
        glFramebufferTexture2D(GL_FRAMEBUFFER, GL_COLOR_ATTACHMENT0, GL_TEXTURE_2D, $colorTex, 0);
        glDrawBuffers(1, GL_COLOR_ATTACHMENT0);
        glViewport(0, 0, 64, 64);

        $quad = new QuadVertexArray($this->gl);
        GLValidator::drainErrors();

        $shader->use();
        GLValidator::drainErrors();

        $quad->draw();

        $errors = GLValidator::drainErrors();
        $this->assertEmpty(
            $errors,
            'GL errors during draw with dummy cubemap textures: ' . json_encode($errors)
        );

        glBindFramebuffer(GL_FRAMEBUFFER, 0);
        glDeleteFramebuffers(1, $fbo);
        glDeleteTextures(1, $colorTex);
        foreach ($cubemapIds as $id) {
            glDeleteTextures(1, $id);
        }
    }

    /**
     * Tests GBuffer-style FBO with multiple render targets (MRT).
     */
    public function testGBufferMRTSetup(): void
    {
        $fbo = 0;
        glGenFramebuffers(1, $fbo);
        glBindFramebuffer(GL_FRAMEBUFFER, $fbo);

        $formats = [
            ['internal' => GL_RGB16F, 'format' => GL_RGB, 'type' => GL_FLOAT],
            ['internal' => GL_RGB16F, 'format' => GL_RGB, 'type' => GL_FLOAT],
            ['internal' => GL_RGBA8, 'format' => GL_RGBA, 'type' => GL_UNSIGNED_BYTE],
            ['internal' => GL_RG8, 'format' => GL_RG, 'type' => GL_UNSIGNED_BYTE],
            ['internal' => GL_RGB16F, 'format' => GL_RGB, 'type' => GL_FLOAT],
            ['internal' => GL_R8, 'format' => GL_RED, 'type' => GL_UNSIGNED_BYTE],
        ];

        $textures = [];
        $attachments = [];

        foreach ($formats as $i => $fmt) {
            $tex = 0;
            glGenTextures(1, $tex);
            glBindTexture(GL_TEXTURE_2D, $tex);
            glTexImage2D(GL_TEXTURE_2D, 0, $fmt['internal'], 128, 128, 0, $fmt['format'], $fmt['type'], null);
            glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
            glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
            glFramebufferTexture2D(GL_FRAMEBUFFER, GL_COLOR_ATTACHMENT0 + $i, GL_TEXTURE_2D, $tex, 0);
            $textures[] = $tex;
            $attachments[] = GL_COLOR_ATTACHMENT0 + $i;
        }

        $depthTex = 0;
        glGenTextures(1, $depthTex);
        glBindTexture(GL_TEXTURE_2D, $depthTex);
        glTexImage2D(GL_TEXTURE_2D, 0, GL_DEPTH_COMPONENT24, 128, 128, 0, GL_DEPTH_COMPONENT, GL_FLOAT, null);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_NEAREST);
        glFramebufferTexture2D(GL_FRAMEBUFFER, GL_DEPTH_ATTACHMENT, GL_TEXTURE_2D, $depthTex, 0);

        glDrawBuffers(count($attachments), ...$attachments);

        $status = glCheckFramebufferStatus(GL_FRAMEBUFFER);
        $this->assertEquals(GL_FRAMEBUFFER_COMPLETE, $status, 'GBuffer FBO not complete, status: 0x' . dechex($status));

        GLValidator::check('GBuffer MRT setup');

        glViewport(0, 0, 128, 128);
        glClearColor(0.0, 0.0, 0.0, 0.0);
        glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);

        GLValidator::check('GBuffer clear');

        glBindFramebuffer(GL_FRAMEBUFFER, 0);
        glDeleteFramebuffers(1, $fbo);
        foreach ($textures as $tex) {
            glDeleteTextures(1, $tex);
        }
        glDeleteTextures(1, $depthTex);
    }

    public function testGLValidatorCollectorIntegration(): void
    {
        $validator = new GLValidator();

        glEnable(99999); // generate GL_INVALID_ENUM

        $validator->collect('test context');
        $this->assertTrue($validator->hasErrors());

        $formatted = $validator->formatErrors();
        $this->assertStringContainsString('GL_INVALID_ENUM', $formatted);
        $this->assertStringContainsString('test context', $formatted);

        $validator->clear();
        $this->assertFalse($validator->hasErrors());
    }
}
