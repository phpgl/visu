<?php

namespace VISU\Graphics\Rendering\Renderer;

use Exception;
use GL\Buffer\FloatBuffer;
use GL\Math\Vec2;
use GL\Math\Vec3;
use VISU\Component\DynamicTextLabelComponent;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\BasicVertexArray;
use VISU\Graphics\Font\BitmapFontAtlas;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\Renderer\TextLabelRenderer\TextLabel;
use VISU\Graphics\Rendering\Renderer\TextLabelRenderer\TextLabelAlign;
use VISU\Graphics\Rendering\Renderer\TextLabelRenderer\TextLabelRenderGroup;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class TextLabelRenderer
{   
    /**
     * The loaded bitmap fonts
     * 
     * @var array<string, BitmapFontAtlas>
     */
    private array $loadedFonts = [];

    /**
     * The loaded font textures
     * 
     * @var array<string, Texture>
     */
    private array $loadedFontTextures = [];

    /**
     * An array of render groups
     * 
     * @var array<string, TextLabelRenderGroup>
     */
    private array $renderGroups = [];

    /**
     * The label rendering shader
     */
    private ShaderProgram $shaderProgram;

    /**
     * Constructor 
     * 
     * @param GLState $gl The current GL state.
     * @param ShaderCollection $shaders The shader collection to use.
     */
    public function __construct(
        private GLState $gl,
        ShaderCollection $shaders
    )
    {
        // create the shader program
        $this->shaderProgram = new ShaderProgram($gl);

        // attach a simple vertex shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;
        layout (location = 1) in vec2 a_uv;
        layout (location = 2) in vec4 a_color;

        out vec2 v_uv;
        out vec4 v_color;

        uniform mat4 projection;
        uniform mat4 view;
        uniform bool is_static;

        void main()
        {
            v_uv = vec2(a_uv.x, 1.0f - a_uv.y);
            v_color = a_color;
            if (is_static) {
                gl_Position = projection * vec4(a_position, 1.0f);
                return;
            }
            gl_Position = projection * view * vec4(a_position, 1.0f);
        }
        GLSL));

        // also attach a simple fragment shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        out vec4 fragment_color;

        in vec2 v_uv;
        in vec4 v_color;

        uniform sampler2D font;

        void main()
        {
            float alpha = texture(font, v_uv).a;
            fragment_color = v_color * vec4(1.0f, 1.0f, 1.0f, alpha);
            if (alpha < 0.1f) {
                discard;
            }
        }
        GLSL));
        $this->shaderProgram->link();

    }

    /**
     * Loads a bitmap font under the given handle.
     * 
     * The handle acts as a simple identifier for the font.
     */
    public function loadFont(string $handle, BitmapFontAtlas $fontAtlas) : void
    {
        if (isset($this->loadedFonts[$handle])) {
            throw new \Exception("Font with handle '$handle' already loaded");
        }

        $this->loadedFonts[$handle] = $fontAtlas;

        // create & load the font texture
        $this->loadedFontTextures[$handle] = new Texture($this->gl, 'font.' . $handle);
        $fontTextureOpt = new TextureOptions;
        $fontTextureOpt->minFilter = GL_NEAREST;
        $fontTextureOpt->magFilter = GL_NEAREST;
        $this->loadedFontTextures[$handle]->loadFromFile((string)$fontAtlas->texturePath, $fontTextureOpt);
    }

    /**
     * Unloads a bitmap font under the given handle.
     */
    public function unloadFont(string $handle) : void
    {
        if (!isset($this->loadedFonts[$handle])) {
            throw new \Exception("Font with handle '$handle' not loaded");
        }

        unset($this->loadedFonts[$handle]);
        unset($this->loadedFontTextures[$handle]);
    }

    /**
     * Returns the default font handle, aka the first font that was loaded.
     */
    private function getDefaultFontHandle() : string
    {
        if (empty($this->loadedFonts)) {
            throw new \Exception('You need to load at least one font before you can use the text label renderer.');
        }

        return array_key_first($this->loadedFonts);
    }

    /**
     * Creates a label 
     * 
     * A label is bacically just a piece of text with a position, font and color.
     * Keep a good look on the "renderGroup" every label is assigned to a render group.
     * Labels with the same render group will be rendered together in one draw call.
     * This means when you change only one label in a group, the entire group will be re-rendered and uploaded to the GPU.
     */
    public function createLabel(
        string $text, 
        ?string $fontHandle = null, 
        ?string $renderGroup = 'default', 
        ?Transform $transform = null, 
        bool $isStatic = false
    ) : TextLabel
    {
        if ($fontHandle === null) {
            $fontHandle = $this->getDefaultFontHandle();
        }

        if (!isset($this->loadedFonts[$fontHandle])) {
            throw new \Exception("Font with handle '$fontHandle' not loaded");
        }

        $renderGroupHandle = $renderGroup . ':' . $fontHandle . ':s' . (int) $isStatic;

        if (!isset($this->renderGroups[$renderGroupHandle])) {
            $this->renderGroups[$renderGroupHandle] = new TextLabelRenderGroup($fontHandle, $isStatic, new BasicVertexArray($this->gl, [3, 2, 4]));
        }

        if ($transform === null) {
            $transform = new Transform;
        }

        $label = new TextLabel($text, $transform);
        $this->renderGroups[$renderGroupHandle]->addLabel($label);

        return $label;
    }

    /**
     * Destroys a label
     */
    public function destroyLabel(TextLabel $label) : void
    {
        // remove the label from all render groups
        foreach($this->renderGroups as $renderGroup) {
            $renderGroup->removeLabel($label);
        }

        // remove all empty render groups
        foreach($this->renderGroups as $renderGroupHandle => $renderGroup) {
            if (empty($renderGroup->getLabels())) {
                unset($this->renderGroups[$renderGroupHandle]);
            }
        }
    }

    /**
     * Will update the internal label groups to match the current state of the entities.
     */
    public function synchroniseWithEntites(EntitiesInterface $entities) : void
    {
        // dynamic labels are updated every tick
        foreach($entities->view(DynamicTextLabelComponent::class) as $entity => $dynamicLabel) 
        {
            $transform = $entities->get($entity, Transform::class);

            // if the entity has no internal label state yet, create it
            if (!$entities->has($entity, TextLabel::class)) {
                $internalLabel = $entities->attach($entity, $this->createLabel(
                    $dynamicLabel->text, 
                    $dynamicLabel->fontHandle, 
                    $dynamicLabel->renderGroup, 
                    $transform, 
                    $dynamicLabel->isStatic
                ));
            } else {
                $internalLabel = $entities->get($entity, TextLabel::class);
            }

            // update the internal label state
            $internalLabel->updateText($dynamicLabel->text);
            $internalLabel->updateColor($dynamicLabel->color);
        }

        // cleanup by finding all labels that are not attached to a dynamic label component anymore
        // @TODO we need ECS hooks!
    }

    /**
     * Rebuilds the render group
     */
    private function rebuildRenderGroup(TextLabelRenderGroup $renderGroup) : void
    {
        $vertices = new FloatBuffer();
        $font = $this->loadedFonts[$renderGroup->fontHandle];

        foreach($renderGroup->getLabels() as $label) 
        {
            $x = 0;
            $y = 0;
            $lineHeight = 30;
            $scale = 1.0;
    
            // determine the text length
            $textLen = mb_strlen($label->text);

            // the vertex count has to be counted 
            // in the loop because some chars are not in the atlas
            $vertexCount = 0;

            // fetch the matrix
            $matrix = $label->transform->getLocalMatrix();

            // first we need to calculate the bounds of the text
            // to be able to align it correctly
            $textWidth = 0;
            $textHeight = 0;
            for($i = 0; $i < $textLen; $i++) 
            {
                $char = mb_substr($label->text, $i, 1);
                if ($charData = $font->getCharacterForC($char)) {
                    $textWidth += $charData->xAdvance * $scale;
                    $textHeight = max($textHeight, $charData->height * $scale);
                }
            }

            // for every character in the text
            for($i = 0; $i < $textLen; $i++) 
            {
                $char = mb_substr($label->text, $i, 1);
                $charData = $font->getCharacterForC($char);

                // // on linebreak
                // if ($char === "\n") {
                //     $x = $dtext->offsetX;
                //     $y += $lineHeight * $scale;
                //     continue;
                // }
                
                // skip unknown characters
                if ($charData === null) {
                    continue;
                }

                // create 2 triangles for the character
                $xpos = $x + $charData->xOffset * $scale;
                $ypos = $y + -$charData->yOffset * $scale;
                $w = $charData->width * $scale;
                $h = -$charData->height * $scale;

                if ($label->align === TextLabelAlign::center) {
                    $xpos -= $textWidth / 2;
                } else if ($label->align === TextLabelAlign::right) {
                    $xpos -= $textWidth;
                }

                // for now always align in vertical center
                $ypos += $textHeight / 2;

                // precalucalte the correct uv coordinates for the character
                $uvX = (float) $charData->x / $font->textureWidth;
                $uvY = (float) $charData->y / $font->textureHeight;
                $uvW = (float) $charData->width / $font->textureWidth;
                $uvH = (float) $charData->height / $font->textureHeight;

                // triangles are CCW so we can cull backfaces
                $p1 = new Vec3($xpos, $ypos + $h, 0);
                $p2 = new Vec3($xpos + $w, $ypos, 0);
                $p3 = new Vec3($xpos, $ypos, 0);
                $p4 = new Vec3($xpos, $ypos + $h, 0);
                $p5 = new Vec3($xpos + $w, $ypos + $h, 0);
                $p6 = new Vec3($xpos + $w, $ypos, 0);

                // tranform the point
                $p1 = $matrix * $p1;
                $p2 = $matrix * $p2;
                $p3 = $matrix * $p3;
                $p4 = $matrix * $p4;
                $p5 = $matrix * $p5;
                $p6 = $matrix * $p6;

                // first triangel
                $vertices->pushVec3($p1);
                $vertices->pushVec2(new Vec2($uvX, $uvY + $uvH));
                $vertices->pushVec4($label->color);

                $vertices->pushVec3($p2);
                $vertices->pushVec2(new Vec2($uvX + $uvW, $uvY));
                $vertices->pushVec4($label->color);

                $vertices->pushVec3($p3);
                $vertices->pushVec2(new Vec2($uvX, $uvY));
                $vertices->pushVec4($label->color);

                // second triangle
                $vertices->pushVec3($p4);
                $vertices->pushVec2(new Vec2($uvX, $uvY + $uvH));
                $vertices->pushVec4($label->color);

                $vertices->pushVec3($p5);
                $vertices->pushVec2(new Vec2($uvX + $uvW, $uvY + $uvH));
                $vertices->pushVec4($label->color);

                $vertices->pushVec3($p6);
                $vertices->pushVec2(new Vec2($uvX + $uvW, $uvY));
                $vertices->pushVec4($label->color);

                $vertexCount += 9;

                // advance the cursor
                $x += $charData->xAdvance * $scale;
            }

            $label->markClean();
        }

        $renderGroup->vertexArray->upload($vertices);
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        RenderTargetResource $renderTarget,
    ) : void
    {
        $pipeline->addPass(new CallbackPass(
            'TextLabel',
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) use ($renderTarget)
            {
                $pipeline->writes($pass, $renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) use ($renderTarget)
            {
                $resources->activateRenderTarget($renderTarget);
                $cameraData = $data->get(CameraData::class);

                $this->shaderProgram->use();
                $this->shaderProgram->setUniformMat4('projection', false, $cameraData->projection);
                $this->shaderProgram->setUniformMat4('view', false, $cameraData->view);
                $this->shaderProgram->setUniform1i('font', 0);

                // pipeline settings 
                glDisable(GL_DEPTH_TEST);
                glDisable(GL_BLEND);
                glEnable(GL_CULL_FACE);
                
                foreach($this->renderGroups as $renderGroup) 
                {
                    if ($renderGroup->requiresRebuild()) {
                        $this->rebuildRenderGroup($renderGroup);
                    }

                    $this->loadedFontTextures[$renderGroup->fontHandle]->bind(GL_TEXTURE0);

                    $this->shaderProgram->setUniform1i('is_static', $renderGroup->isStatic);

                    $renderGroup->vertexArray->bind();
                    $renderGroup->vertexArray->drawAll();
                }
            }
        ));
    }
}
