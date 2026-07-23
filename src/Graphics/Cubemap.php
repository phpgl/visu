<?php 

namespace VISU\Graphics;

use GL\Buffer\BufferInterface;
use GL\Buffer\FloatBuffer;
use GL\Math\Mat4;
use GL\Math\Vec3;
use GL\Texture\Texture2D;
use VISU\Exception\VISUException;
use VISU\Graphics\Exception\TextureLoadException;

class Cubemap
{   
    /**
     * @var array<Mat4>|null  The view matrices for capturing each cubemap face
     */
    private static ?array $captureViews = null;

    /**
     * Returns the view matrices for capturing each cubemap face
     * 
     * @return array<Mat4>
     */
    public static function captureViews() : array
    {
        if (self::$captureViews !== null) {
            return self::$captureViews;
        }

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

        self::$captureViews = $captureViews;
        return self::$captureViews;
    }

    /**
     * Returns a projection matrix for capturing cubemap faces
     */
    public static function captureProjectionMatrix() : Mat4
    {
        $captureProjection = new Mat4();
        $captureProjection->perspective(\GL\Math\GLM::radians(90.0), 1.0, 0.1, 10.0);
        return $captureProjection;
    }

    /**
     * The GL texture id / handle
     */
    public int $id = 0;

    /**
     * The cubemap face size (width and height are equal for cubemaps)
     */
    private int $size = 0;

    /**
     * The texture options object
     */
    protected ?TextureOptions $options = null;

    /**
     * Cubemap face targets in order:
     * +X, -X, +Y, -Y, +Z, -Z
     */
    public const FACE_TARGETS = [
        GL_TEXTURE_CUBE_MAP_POSITIVE_X,
        GL_TEXTURE_CUBE_MAP_NEGATIVE_X,
        GL_TEXTURE_CUBE_MAP_POSITIVE_Y,
        GL_TEXTURE_CUBE_MAP_NEGATIVE_Y,
        GL_TEXTURE_CUBE_MAP_POSITIVE_Z,
        GL_TEXTURE_CUBE_MAP_NEGATIVE_Z,
    ];

    /**
     * Constructor
     * 
     * @param GLState $gl The GL state object
     * @param string $name A unique name for the cubemap, used for debugging and resource management
     */
    public function __construct(
        private GLState $gl, 
        public string $name,
    )
    {
        glGenTextures(1, $this->id);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        glDeleteTextures(1, $this->id);

        // remove this cubemap from all tracked bindings
        foreach ($this->gl->currentTextures as $unit => &$targets) {
            if (isset($targets[GL_TEXTURE_CUBE_MAP]) && $targets[GL_TEXTURE_CUBE_MAP] === $this->id) {
                $targets[GL_TEXTURE_CUBE_MAP] = 0;
            }
        }
        unset($targets);
    }

    /**
     * Returns the cubemap face size (width and height are equal)
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Sets the cubemap face size (used when allocating faces externally)
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * Binds the cubemap to the current context and sets the active texture unit 
     * 
     * @param int $unit The texture unit to bind the cubemap to
     */
    public function bind(int $unit = GL_TEXTURE0): void
    {
        $this->gl->bindTexture($unit, GL_TEXTURE_CUBE_MAP, $this->id);
    }
    
    /**
     * Applies the textures minification and magnification filter parameters
     */
    private function applyFilterParameters(): void
    {
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MIN_FILTER, $this->options->minFilter);
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_MAG_FILTER, $this->options->magFilter);

        // cubemaps always use clamp to edge to prevent seam artifacts
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_S, GL_CLAMP_TO_EDGE);
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_T, GL_CLAMP_TO_EDGE);
        glTexParameteri(GL_TEXTURE_CUBE_MAP, GL_TEXTURE_WRAP_R, GL_CLAMP_TO_EDGE);
    }

    /**
     * Uploads buffer data for a single cubemap face
     * 
     * @param int $faceTarget The cubemap face target (e.g., GL_TEXTURE_CUBE_MAP_POSITIVE_X)
     * @param int $size The width/height of the face (must be square)
     * @param int $internalFormat The internal format of the texture
     * @param int $dataFormat The format of the source data
     * @param int $dataType The type of the source data
     * @param BufferInterface|null $buffer The buffer containing the pixel data
     * @return void 
     */
    public function uploadFaceBuffer(
        int $faceTarget, 
        int $size, 
        int $internalFormat, 
        int $dataFormat, 
        int $dataType, 
        ?BufferInterface $buffer = null
    ): void 
    {
        glTexImage2D(
            $faceTarget,
            0,
            $internalFormat,
            $size,
            $size,
            0,
            $dataFormat,
            $dataType,
            $buffer
        );
    }

    /**
     * Uploads buffers for all 6 cubemap faces
     * 
     * @param array<int, BufferInterface|null> $buffers Array of 6 buffers in face order (+X, -X, +Y, -Y, +Z, -Z)
     * @return void 
     */
    public function uploadBuffers(TextureOptions $options, array $buffers): void
    {
        if (count($buffers) !== 6) {
            throw new TextureLoadException("Cubemap requires exactly 6 face buffers, " . count($buffers) . " provided.");
        }

        // store the options
        $this->options = $options;

        // validate size is set
        if ($options->width === null) {
            throw new TextureLoadException("Width must be set in texture options for cubemap.");
        }

        // cubemaps must be square
        $this->size = $options->width;

        // bind
        $this->bind();

        // apply the texture parameters
        $this->applyFilterParameters();

        glPixelStorei(GL_UNPACK_ALIGNMENT, 4);

        // validate the formats
        if ($options->internalFormat === null) {
            throw new TextureLoadException("Internal format not set in texture options, cannot upload buffer to cubemap.");
        }

        if ($options->dataFormat === null) {
            throw new TextureLoadException("Source format not set in texture options, cannot upload buffer to cubemap.");
        }

        if ($options->dataType === null) {
            throw new TextureLoadException("Source type not set in texture options, cannot upload buffer to cubemap.");
        }

        // upload each face
        foreach (self::FACE_TARGETS as $index => $faceTarget) {
            $this->uploadFaceBuffer(
                $faceTarget,
                $this->size,
                $options->internalFormat,
                $options->dataFormat,
                $options->dataType,
                $buffers[$index]
            );
        }

        // generate mipmaps if requested
        if ($options->generateMipmaps) {
            glGenerateMipmap(GL_TEXTURE_CUBE_MAP);
        }
    }

    /**
     * Loads a cubemap from 6 image files on disk
     * 
     * @param array<int, string> $paths Array of 6 file paths in face order (+X, -X, +Y, -Y, +Z, -Z)
     * 
     * @throws TextureLoadException 
     */
    public function loadFromFiles(array $paths, ?TextureOptions $options = null): void
    {
        if (count($paths) !== 6) {
            throw new TextureLoadException("Cubemap requires exactly 6 face images, " . count($paths) . " provided.");
        }

        if ($options === null) {
            $options = new TextureOptions();
        }

        // store the options
        $this->options = $options;

        // bind
        $this->bind();

        // apply the texture parameters
        $this->applyFilterParameters();

        glPixelStorei(GL_UNPACK_ALIGNMENT, 4);

        $firstSize = null;

        foreach (self::FACE_TARGETS as $index => $faceTarget) {
            $path = $paths[$index];

            if (!file_exists($path) || !is_readable($path)) {
                throw new TextureLoadException("Cubemap face image not found or not accessible: {$path}");
            }

            $textureData = Texture2D::fromDisk($path);

            // validate all faces have the same size
            $faceSize = $textureData->width();
            if ($textureData->width() !== $textureData->height()) {
                throw new TextureLoadException("Cubemap face must be square, got {$textureData->width()}x{$textureData->height()} for: {$path}");
            }

            if ($firstSize === null) {
                $firstSize = $faceSize;
                $this->size = $faceSize;
            } elseif ($faceSize !== $firstSize) {
                throw new TextureLoadException("All cubemap faces must have the same size. Expected {$firstSize}, got {$faceSize} for: {$path}");
            }

            switch ($textureData->channels()) {
                case 4:
                    $guessedInternalFormat = $options->isSRGB ? GL_SRGB_ALPHA : GL_RGBA;
                    $guessedSourceFormat = GL_RGBA;
                    break;
                case 3:
                    $guessedInternalFormat = $options->isSRGB ? GL_SRGB : GL_RGB;
                    $guessedSourceFormat = GL_RGB;
                    break;
                case 2:
                    $guessedInternalFormat = GL_RG;
                    $guessedSourceFormat = GL_RG;
                    break;
                case 1:
                    $guessedInternalFormat = GL_RED;
                    $guessedSourceFormat = GL_RED;
                    break;
                default:
                    throw new TextureLoadException("Unsupported number of channels: {$textureData->channels()}");
            }

            if ($options->internalFormat === null) {
                $options->internalFormat = $guessedInternalFormat;
            }

            if ($options->dataFormat === null) {
                $options->dataFormat = $guessedSourceFormat;
            }

            if ($options->dataType === null) {
                $options->dataType = GL_UNSIGNED_BYTE;
            }

            glTexImage2D(
                $faceTarget,
                0,
                $options->internalFormat,
                $faceSize,
                $faceSize,
                0,
                $options->dataFormat,
                $options->dataType,
                $textureData->buffer()
            );
        }

        // generate mipmaps if requested
        if ($options->generateMipmaps) {
            glGenerateMipmap(GL_TEXTURE_CUBE_MAP);
        }
    }

    /**
     * Allocates an empty cubemap with the given size and options
     * 
     * @param int $size The width/height of each face (cubemaps are always square)
     */
    public function allocateEmpty(int $size, ?TextureOptions $options = null): void
    {
        if ($options === null) {
            $options = new TextureOptions();
        }

        if ($options->internalFormat === null) {
            $options->internalFormat = GL_RGBA;
        }

        if ($options->dataFormat === null) {
            $options->dataFormat = GL_RGBA;
        }

        if ($options->dataType === null) {
            $options->dataType = GL_UNSIGNED_BYTE;
        }

        $options->width = $size;
        $options->height = $size;

        // create array of null buffers for each face
        $buffers = array_fill(0, 6, null);

        $this->uploadBuffers($options, $buffers);
    }

    /**
     * Reads a face of the cubemap back into a float buffer
     * 
     * @param int $faceIndex The face index (0-5 for px, nx, py, ny, pz, nz)
     * @return FloatBuffer
     */
    public function readFace(int $faceIndex): FloatBuffer
    {
        if ($faceIndex < 0 || $faceIndex >= 6) {
            throw new \InvalidArgumentException("Face index must be between 0 and 5");
        }

        // create framebuffer for resource management
        $framebuffer = new Framebuffer($this->gl);
        $framebuffer->attachTextureId(GL_COLOR_ATTACHMENT0, self::FACE_TARGETS[$faceIndex], $this->id);
        
        // check framebuffer completeness
        if (!$framebuffer->isValid($status, $error)) {
            throw new VISUException("Framebuffer not complete for reading cubemap face: " . $error);
        }

        // read the texture into a float buffer
        $buffer = new FloatBuffer();
        glReadPixels(0, 0, $this->size, $this->size, GL_RGB, GL_FLOAT, $buffer);

        return $buffer;
    }
}
