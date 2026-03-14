<?php

namespace Tests\Component;

use PHPUnit\Framework\TestCase;
use VISU\Component\Camera3DMode;

class Camera3DModeTest extends TestCase
{
    public function testEnumCases(): void
    {
        $this->assertCount(3, Camera3DMode::cases());
        $this->assertSame('orbit', Camera3DMode::orbit->name);
        $this->assertSame('firstPerson', Camera3DMode::firstPerson->name);
        $this->assertSame('thirdPerson', Camera3DMode::thirdPerson->name);
    }
}
