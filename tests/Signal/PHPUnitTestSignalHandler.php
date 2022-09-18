<?php 

namespace VISU\Tests\Signal;

use VISU\Signals\ResponseSignal;
use VISU\HTTP\Response;

use ClanCats\Container\Container;

class PHPUnitTestSignalHandler
{
    protected $container;

    /**
     * Construct
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
}
