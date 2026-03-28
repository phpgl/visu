<?php

namespace VISU\Tests\Benchmark;

use VISU\Signal\Dispatcher;
use VISU\Signal\Signal;

class SignalBench
{
    private Dispatcher $dispatcher;
    private Signal $signal;

    public function setUp(): void
    {
        $this->dispatcher = new Dispatcher();
        $this->signal = new Signal('test.event');

        // register 10 handlers
        for ($i = 0; $i < 10; $i++) {
            $this->dispatcher->register('test.event', function (Signal $signal) {
                // noop handler
            });
        }
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchDispatch10Handlers(): void
    {
        $this->dispatcher->dispatch('test.event', $this->signal);
    }

    /**
     * @Revs(10000)
     * @Iterations(5)
     */
    public function benchRegisterHandler(): void
    {
        $d = new Dispatcher();
        for ($i = 0; $i < 100; $i++) {
            $d->register('event_' . $i, function () {});
        }
    }
}
