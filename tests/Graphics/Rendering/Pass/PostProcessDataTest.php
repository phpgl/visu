<?php

namespace Tests\Graphics\Rendering\Pass;

use PHPUnit\Framework\TestCase;
use VISU\Graphics\Rendering\Pass\PostProcessData;

class PostProcessDataTest extends TestCase
{
    public function testInstantiation(): void
    {
        $data = new PostProcessData();
        $this->assertInstanceOf(PostProcessData::class, $data);
    }
}
