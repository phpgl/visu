<?php

namespace VISU\Graphics\Rendering\Renderer;

use GL\Buffer\FloatBuffer;
use GL\Math\Mat4;
use VISU\Graphics\Exception\BitmapFontException;
use VISU\Graphics\Font\BitmapFontAtlas;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\Graphics\Texture;

class DebugOverlayTextRenderer
{   
    /**
     * Loads the cozette font and returns a BitmapFontAtlas.
     */
    public static function loadDebugFontAtlas() : BitmapFontAtlas
    {
        return require VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/cozette/cozette.php';
    }

    /**
     * The GL texture id of the font atlas
     */
    private Texture $fontTexture;

    /**
     * GL Vertex buffer object id
     */
    private int $VBO = 0; 

    /**
     * GL Vertex array object id
     */
    private int $VAO = 0;

    /**
     * The debug font shader program.
     */
    private ShaderProgram $shaderProgram;

    /**
     * Constructor 
     * 
     * @param GLState $glstate The current GL state.
     * @param BitmapFontAtlas $fontAtlas The font atlas to use.
     */
    public function __construct(
        private GLState $glstate,
        private BitmapFontAtlas $fontAtlas, 
    )
    {
        // the font altas needs to contain a texture path for the debug font renderer to work.
        if ($this->fontAtlas->texturePath === null) {
            throw new BitmapFontException('The font atlas needs to contain a texture path, so it can be used with the debug font renderer.');
        }

        // load the font texture 
        $this->fontTexture = new Texture($this->glstate, 'debug_font');
        $this->fontTexture->loadFromFile($this->fontAtlas->texturePath);

        // build the vertex array and buffer objects
        $this->createVAO();

        // create the shader program
        $this->shaderProgram = new ShaderProgram($glstate);

        // attach a simple vertex shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec2 a_position;
        layout (location = 1) in vec2 a_uv;

        out vec2 v_uv;

        uniform mat4 projection;

        void main()
        {
            v_uv = vec2(a_uv.x, 1.0f - a_uv.y);
            gl_Position = projection * vec4(a_position, 0.0f, 1.0f);
        }
        GLSL));

        // also attach a simple fragment shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        out vec4 fragment_color;

        in vec2 v_uv;

        uniform sampler2D font;

        void main()
        {
            fragment_color = vec4(vec3(texture(font, v_uv).a), 1.0f);
        }
        GLSL));
        $this->shaderProgram->link();
    }

    /**
     * Creates the vertex array and buffer objects
     */
    private function createVAO() : void
    {
        glGenVertexArrays(1, $this->VAO);
        glGenBuffers(1, $this->VBO);

        glBindVertexArray($this->VAO);
        glBindBuffer(GL_ARRAY_BUFFER, $this->VBO);

        // vertex attributes for the text
        // position
        glEnableVertexAttribArray(0);
        glVertexAttribPointer(0, 2, GL_FLOAT, false, GL_SIZEOF_FLOAT * 4, 0);

        // uv
        glEnableVertexAttribArray(1);
        glVertexAttribPointer(1, 2, GL_FLOAT, false, GL_SIZEOF_FLOAT * 4, GL_SIZEOF_FLOAT * 2);

        glBindBuffer(GL_ARRAY_BUFFER, 0);
        glBindVertexArray(0);
    }

    /**
     * Fills the given buffer object with the vertices for the given text.
     * 
     * @return int The number of vertices that were written to the buffer.
     */
    private function fillVertexBufferForText(RenderTarget $renderTarget, FloatBuffer $vertices, string $text) : int
    {
        $leftMargin = 0;
        $topMargin = 0;
        $maxWidth = 800;

        $x = $leftMargin;
        $y = $topMargin;
        $scale = $renderTarget->contentScaleX; 
        $lineHeight = 20;

        // determine the text length
        $textLen = mb_strlen($text);

        // the vertex count has to be counted 
        // in the loop because some chars are not in the atlas
        $vertexCount = 0;
        
        // reserve the memory for the vertices
        $vertices->reserve($textLen * 6 * 4);
        
        // for every character in the text
        for($i = 0; $i < $textLen; $i++) 
        {
            $char = mb_substr($text, $i, 1);
            $charData = $this->fontAtlas->getCharacterForC($char);

            // on linebreak
            if ($char === "\n") {
                $x = $leftMargin;
                $y += $lineHeight * $scale;
                continue;
            }
            
            // skip unknown characters
            if ($charData === null) {
                continue;
            }

            // create 2 triangles for the character
            // precalucalte the correct uv coordinates for the character
            $xpos = $x + $charData->xOffset * $scale;
            $ypos = $y + $charData->yOffset * $scale;
            $w = $charData->width * $scale;
            $h = $charData->height * $scale;
            $uvX = (float) $charData->x / $this->fontAtlas->textureWidth;
            $uvY = (float) $charData->y / $this->fontAtlas->textureHeight;
            $uvW = (float) $charData->width / $this->fontAtlas->textureWidth;
            $uvH = (float) $charData->height / $this->fontAtlas->textureHeight;

            $vertices->pushArray([
                $xpos, $ypos, $uvX, $uvY,
                $xpos + $w, $ypos, $uvX + $uvW, $uvY,
                $xpos, $ypos + $h, $uvX, $uvY + $uvH,
                $xpos + $w, $ypos, $uvX + $uvW, $uvY,
                $xpos, $ypos + $h, $uvX, $uvY + $uvH,
                $xpos + $w, $ypos + $h, $uvX + $uvW, $uvY + $uvH,
            ]);

            $vertexCount += 6;

            // advance the cursor
            $x += $charData->xAdvance * $scale;
            if ($x > $maxWidth) {
                $x = $leftMargin;
                $y += $lineHeight * $scale;
            }
        }

        return $vertexCount;
    }

    /**
     * Attaches a render pass to the pipeline
     * 
     * @param RenderPipeline $pipeline 
     * @param array<DebugOverlayText> $texts 
     */
    public function attachPass(
        RenderPipeline $pipeline, 
        array $texts
    ) : void
    {
        $pipeline->addPass(new CallbackPass(
            function(RenderPipeline $pipeline, PipelineContainer $data) {
            },
            function(PipelineContainer $data, PipelineResources $resources) use ($texts) 
            {
                $renderTarget = $resources->getActiveRenderTarget();

                $width = $renderTarget->width();
                $height = $renderTarget->height();

                $projection = new Mat4;
                $projection->ortho(0, $width, $height, 0, -1, 1);

                // activate the shader program
                $this->shaderProgram->use();
                $this->shaderProgram->setUniformMat4('projection', false, $projection);

                // bind the font texture
                $this->fontTexture->bind(GL_TEXTURE0);
                $this->shaderProgram->setUniform1i('font', 0);

                // create a buffer for the vertices
                $vertices = new FloatBuffer();

                // fill the buffer with the vertices for the text
                $vertexCount = $this->fillVertexBufferForText($renderTarget, $vertices, $texts[0]->text);

                // bind the vertex array and buffer
                if ($this->glstate->currentVertexArray !== $this->VAO) {
                    $this->glstate->currentVertexArray = $this->VAO;
                    glBindVertexArray($this->VAO);
                }

                glBindBuffer(GL_ARRAY_BUFFER, $this->VBO);

                // fill the buffer with the vertices
                glBufferData(GL_ARRAY_BUFFER, $vertices, GL_DYNAMIC_DRAW);

                // draw the text
                glDrawArrays(GL_TRIANGLES, 0, $vertexCount);

                // unbind the vertex array and buffer
                glBindBuffer(GL_ARRAY_BUFFER, 0);
                glBindVertexArray(0);
            },
        ));
    }    
}
