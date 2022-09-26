<?php 

namespace VISU\Tests\Signals\Input;

use VISU\Signals\Input\KeySignal;

class KeySignalTest extends \PHPUnit\Framework\TestCase
{
    public function testSignalConstruction()
    {
        $signal = new KeySignal(
            $this->createMock(\VISU\OS\Window::class),
            1,
            2,
            3,
            4
        );

        $this->assertInstanceOf(KeySignal::class, $signal);

        $this->assertEquals(1, $signal->key);
        $this->assertEquals(2, $signal->scancode);
        $this->assertEquals(3, $signal->action);
        $this->assertEquals(4, $signal->mods);
    }
}