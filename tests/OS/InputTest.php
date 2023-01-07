<?php 

namespace VISU\Tests\OS;

use VISU\OS\CursorMode;
use VISU\OS\Input;
use VISU\Signal\VoidDispatcher;
use VISU\Tests\GLContextTestCase;


/**
 * @group glfwinit
 */
class InputTest extends GLContextTestCase
{
    public function testSetCursorMode()
    {
        $input = new Input($this->createWindow(), new VoidDispatcher);

        $input->setCursorMode(CursorMode::NORMAL);
        $this->assertEquals(CursorMode::NORMAL, $input->getCursorMode());

        // $input->setCursorMode(CursorMode::HIDDEN);
        // $this->assertEquals(CursorMode::HIDDEN, $input->getCursorMode());

        // $input->setCursorMode(CursorMode::DISABLED);
        // $this->assertEquals(CursorMode::DISABLED, $input->getCursorMode());

    }
}