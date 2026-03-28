<?php

namespace Tests\Graphics\Rendering\Pass;

use PHPUnit\Framework\TestCase;
use VISU\Graphics\Rendering\Pass\PostProcessData;

class BloomPassTest extends TestCase
{
    public function testPostProcessDataStructure(): void
    {
        $data = new PostProcessData();
        $this->assertInstanceOf(PostProcessData::class, $data);
    }
}
