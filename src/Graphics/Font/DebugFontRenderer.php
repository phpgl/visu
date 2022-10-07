<?php

namespace VISU\Graphics\Font;

use GL\Buffer\FloatBuffer;
use GL\Math\Mat4;
use GL\Texture\Texture2D;
use VISU\Graphics\Exception\BitmapFontException;
use VISU\Graphics\GLState;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

/**
 * DebugFontRenderer is a simple font renderer that uses the "cozette" font.
 * This class is not built to be extenable, I just needed a tool to render out 
 * text to the screen for debugging purposes. (FPS etc..)
 */
class DebugFontRenderer
{
    /**
     * Loads the cozette font and returns a BitmapFontAtlas.
     */
    public static function loadDebugFontAtlas() : BitmapFontAtlas
    {
        return require VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/cozette/cozette.php';
    }

    /**
     * The currently used font atlas.
     */
    private BitmapFontAtlas $fontAtlas;

    /**
     * The GL texture id of the font atlas
     */
    private int $textureId = 0;

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
     * 
     * @var ShaderProgram
     */
    private ShaderProgram $shaderProgram;

    /**
     * Constructor 
     * 
     * @param BitmapFontAtlas $atlas The font atlas to use.
     */
    public function __construct(BitmapFontAtlas $atlas, GLState $glstate)
    {
        $this->fontAtlas = $atlas;

        // the font altas needs to contain a texture path for the debug font renderer to work.
        if ($this->fontAtlas->texturePath === null) {
            throw new BitmapFontException('The font atlas needs to contain a texture path, so it can be used with the debug font renderer.');
        }

        // load the font texture 
        $this->loadFontTexture($this->fontAtlas->texturePath);

        // create the shader program
        $this->shaderProgram = new ShaderProgram($glstate);

        $this->createVAO();

        // attach a simple vertex shader
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec2 a_position;
        layout (location = 1) in vec2 a_uv;

        out vec2 v_uv;

        uniform mat4 projection;

        void main()
        {
            v_uv = a_uv;
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

    public function __destruct()
    {
        glDeleteTextures(1, $this->textureId);
        glDeleteBuffers(1, $this->VBO);
        glDeleteVertexArrays(1, $this->VAO);
    }

    /**
     * Loads the bitmap font atlas texture into memory.
     */
    private function loadFontTexture(string $path) : void
    {
        glGenTextures(1, $this->textureId);
        glActiveTexture(GL_TEXTURE0);
        glBindTexture(GL_TEXTURE_2D, $this->textureId);

        // texture wrapping
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_S, GL_CLAMP_TO_EDGE);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_T, GL_CLAMP_TO_EDGE);

        // set texture filtering parameters
        // because we have a pixel font we want to use nearest neighbor filtering
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, GL_NEAREST);
        glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, GL_NEAREST);

        $textureData = Texture2D::fromDisk($path);

        if ($textureData->channels() !== 4) {
            throw new BitmapFontException('The font atlas texture needs to be a RGBA texture.');
        }
        
        glTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA, $textureData->width(), $textureData->height(), 0, GL_RGBA, GL_UNSIGNED_BYTE, $textureData->buffer());
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
        glVertexAttribPointer(0, 2, GL_FLOAT, GL_FALSE, GL_SIZEOF_FLOAT * 4, 0);

        // uv
        glEnableVertexAttribArray(1);
        glVertexAttribPointer(1, 2, GL_FLOAT, GL_FALSE, GL_SIZEOF_FLOAT * 4, GL_SIZEOF_FLOAT * 2);

        glBindBuffer(GL_ARRAY_BUFFER, 0);
        glBindVertexArray(0);
    }

    /**
     * Fills the given buffer object with the vertices for the given text.
     * 
     * @return int The number of vertices that were written to the buffer.
     */
    private function fillVertexBufferForText(FloatBuffer $vertices, string $text) : int
    {
        $leftMargin = 0;
        $topMargin = 0;
        $maxWidth = 800;

        $x = $leftMargin;
        $y = $topMargin;
        $scale = 1.0;
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

    public function renderText(string $text) : void
    {
        $this->shaderProgram->use();

        // for text rendering we need to use a orthographic projection
        $projection = new Mat4;
        $projection->ortho(0, 1280, 720, 0, -1, 1);
        glViewport(0, 0, 1280 * 2, 720 * 2);

        // set the projection matrix
        $this->shaderProgram->setUniformMat4('projection', false, $projection);

        // bind the font texture
        glActiveTexture(GL_TEXTURE0);
        glBindTexture(GL_TEXTURE_2D, $this->textureId);
        $this->shaderProgram->setUniform1i('font', 0);

        // create a buffer for the vertices
        $vertices = new FloatBuffer();

        // fill the buffer with the vertices for the text
        $vertexCount = $this->fillVertexBufferForText($vertices, $text);

        // bind the vertex array and buffer
        glBindVertexArray($this->VAO);
        glBindBuffer(GL_ARRAY_BUFFER, $this->VBO);

        // fill the buffer with the vertices
        glBufferData(GL_ARRAY_BUFFER, $vertices, GL_DYNAMIC_DRAW);

        // draw the text
        glDrawArrays(GL_TRIANGLES, 0, $vertexCount);

        // unbind the vertex array and buffer
        glBindBuffer(GL_ARRAY_BUFFER, 0);
        glBindVertexArray(0);
    }
}