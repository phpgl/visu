<?php 

namespace VISU\Tests\Signals;

use VISU\Signals\BootstrapSignal;
use ClanCats\Container\Container;

class BootstrapSignalTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $this->assertInstanceOf(BootstrapSignal::class, new BootstrapSignal(new Container));
    }

    public function testResponseGetterAndSetter()
    {
        $container = new Container;
        $signal = new BootstrapSignal($container);

        $this->assertEquals($container, $signal->getContainer());

        $signal->setContainer($container);
        
        $this->assertEquals($container, $signal->getContainer());
    }
}
