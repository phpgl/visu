<?php 

namespace VISU\Command;

use GL\Texture\Texture2D;
use VISU\Graphics\GLState;
use VISU\Graphics\HDRIToCubemap;

class HDRIToCubemapCommand extends Command
{
    /**
     * The commands description displayed when listing commands
     */
    protected ?string $descriptionShort = 'Converts an equirectangular HDRI to 6 cubemap face HDR files.';

    /**
     * The commands description
     */
    protected string $description = <<<'DESC'
Converts an equirectangular HDRI image (.hdr) to 6 separate HDR files,
one for each cubemap face (+X, -X, +Y, -Y, +Z, -Z).

Example usage:
  ./bin/visu tools:hdri_to_cubemap /path/to/environment.hdr --output /path/to/output --size 512
DESC;

    /**
     * Expected arguments
     * 
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [
        'input' => [
            'prefix' => 'i',
            'longPrefix' => 'input',
            'description' => 'Path to the input equirectangular HDRI file (.hdr)',
            'required' => true,
        ],
        'output' => [
            'prefix' => 'o',
            'longPrefix' => 'output',
            'description' => 'Output directory for the cubemap face files (defaults to input directory)',
            'defaultValue' => null,
        ],
        'size' => [
            'prefix' => 's',
            'longPrefix' => 'size',
            'description' => 'Resolution of each cubemap face (default: 512)',
            'defaultValue' => '512',
        ],
        'prefix' => [
            'prefix' => 'p',
            'longPrefix' => 'prefix',
            'description' => 'Prefix for output filenames (default: cubemap_)',
            'defaultValue' => 'cubemap_',
        ],
    ];

    /**
     * Execute this command
     */
    public function execute()
    {
        $inputPath = (string) $this->cli->arguments->get('input');
        $outputDir = $this->cli->arguments->get('output');
        $faceSize = (int) $this->cli->arguments->get('size');
        $prefix = (string) $this->cli->arguments->get('prefix');

        // validate input file
        if (!file_exists($inputPath) || !is_readable($inputPath)) {
            $this->cli->error("Input file not found or not readable: {$inputPath}");
            return;
        }

        // default output directory to input file directory
        if ($outputDir === null) {
            $outputDir = dirname($inputPath);
        } else {
            $outputDir = (string) $outputDir;
        }

        $this->info("Converting HDRI to cubemap faces...");
        $this->info("Input: {$inputPath}");
        $this->info("Output: {$outputDir}");
        $this->info("Face size: {$faceSize}x{$faceSize}");

        // initialize GLFW for headless OpenGL context
        if (!glfwInit()) {
            $this->cli->error("Failed to initialize GLFW");
            return;
        }

        // create a hidden window for OpenGL context
        glfwWindowHint(GLFW_VISIBLE, GLFW_FALSE);
        glfwWindowHint(GLFW_CONTEXT_VERSION_MAJOR, 4);
        glfwWindowHint(GLFW_CONTEXT_VERSION_MINOR, 1);
        glfwWindowHint(GLFW_OPENGL_PROFILE, GLFW_OPENGL_CORE_PROFILE);

        $window = glfwCreateWindow(1, 1, "HDRI to Cubemap", null, null);
        glfwMakeContextCurrent($window);

        try {
            // create GL state and converter
            $gl = new GLState();
            $converter = new HDRIToCubemap($gl);
            $cubemap = $converter->convert($inputPath, $faceSize);
            $faceNames = ['px', 'nx', 'py', 'ny', 'pz', 'nz'];

            // read each face and save as HDR file
            for ($i = 0; $i < 6; $i++) {
                $faceName = $faceNames[$i];
                $outputPath = rtrim($outputDir, '/') . '/' . $prefix . $faceName . '.hdr';

                $this->info("Saving face {$faceName} to {$outputPath}...");

                // read face data from cubemap
                $buffer = $cubemap->readFace($i);

                // use the php-glfw hdr writer...
                $texture = Texture2D::fromBufferHDR($faceSize, $faceSize, $buffer, Texture2D::CHANNEL_RGB);
                $texture->writeHDR($outputPath);
            }

            $this->success("Successfully converted HDRI to 6 cubemap face files");
            $this->info("Face order: +X (px), -X (nx), +Y (py), -Y (ny), +Z (pz), -Z (nz)");

        } catch (\Exception $e) {
            $this->cli->error("Conversion failed: " . $e->getMessage());
        } finally {
            glfwDestroyWindow($window);
            glfwTerminate();
        }
    }
}
