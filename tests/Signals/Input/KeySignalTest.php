<?php 

namespace VISU\Tests\Signals\Input;

use VISU\OS\Key;
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

    public function tesKeyMods()
    {
        $signal = new KeySignal(
            $this->createMock(\VISU\OS\Window::class),
            1,
            2,
            3,
            Key::MOD_SHIFT
        );

        $this->assertTrue($signal->isShiftDown());
        $this->assertFalse($signal->isControlDown());
        $this->assertFalse($signal->isAltDown());
        $this->assertFalse($signal->isSuperDown());

        $signal = new KeySignal(
            $this->createMock(\VISU\OS\Window::class),
            1,
            2,
            3,
            Key::MOD_CONTROL
        );

        $this->assertFalse($signal->isShiftDown());
        $this->assertTrue($signal->isControlDown());
        $this->assertFalse($signal->isAltDown());
        $this->assertFalse($signal->isSuperDown());

        $signal = new KeySignal(
            $this->createMock(\VISU\OS\Window::class),
            1,
            2,
            3,
            Key::MOD_ALT | Key::MOD_SUPER
        );

        $this->assertFalse($signal->isShiftDown());
        $this->assertFalse($signal->isControlDown());
        $this->assertTrue($signal->isAltDown());
        $this->assertTrue($signal->isSuperDown());
    }
}