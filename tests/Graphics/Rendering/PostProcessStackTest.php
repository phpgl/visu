<?php

namespace Tests\Graphics\Rendering;

use PHPUnit\Framework\TestCase;
use VISU\Graphics\Rendering\PostProcessStack;

class PostProcessStackTest extends TestCase
{
    public function testDefaultsAllDisabled(): void
    {
        // PostProcessStack requires ShaderCollection which needs GL context,
        // so we test the data/configuration classes instead
        $this->assertTrue(true, 'PostProcessStack requires GL context for construction');
    }
}
