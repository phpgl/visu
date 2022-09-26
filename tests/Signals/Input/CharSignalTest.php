<?php 

namespace VISU\Tests\Signals\Input;

use VISU\Signals\Input\CharSignal;

class CharSignalTest extends \PHPUnit\Framework\TestCase
{
    public function testSignalConstruction()
    {
        $signal = new CharSignal(
            $this->createMock(\VISU\OS\Window::class),
            1
        );

        $this->assertInstanceOf(CharSignal::class, $signal);
        $this->assertEquals(1, $signal->codepoint);
    }

    public function testSignalGetCharString()
    {
        $signal = new CharSignal(
            $this->createMock(\VISU\OS\Window::class),
            169
        );

        $this->assertEquals('©', $signal->getString());
    }
}