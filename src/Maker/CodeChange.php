<?php 

namespace VISU\Maker;

class CodeChange
{
    /**
     * The path at which the code change shall be applied
     */
    public string $filepath;

    /**
     * The code which shall be injected
     */
    public string $code;

    /**
     * The offset where code shall be injected
     */
    public int $offsetStart = 0;
    public int $offsetEnd = 0;

    /**
     * If true this change should fully override the file, offsets can be ignored
     */
    public bool $fullOverride = true;

    /**
     * Constructor
     */
    public function __construct(string $filepath, string $code)
    {
        $this->filepath = $filepath;
        $this->code = $code;
    }
}
