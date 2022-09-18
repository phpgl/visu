<?php 

namespace VISU\Tests\Signal;

use ClanCats\Container\Container;

use VISU\Signal\Dispatcher;
use VISU\Signal\Signal;

class DispatcherTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $this->assertInstanceOf(Dispatcher::class, new Dispatcher);
    }

    public static function staticDummySignalHandler(Signal $signal)
    {

    }

    public function dummySignalHandler(Signal $signal)
    {

    }

    public function testSignalRegistration()
    {
        $dispatcher = new Dispatcher;

        $dispatcher->register('test', function() {});
        $dispatcher->register('test', [$this, 'dummySignalHandler']);
        $dispatcher->register('test', function() { return 'im last'; }, 10);
        $dispatcher->register('test', function() { return 'im first'; }, -10);

        $handlers = $dispatcher->getSignalHandlersByPriority('test');

        $this->assertEquals('im first', $handlers[0]());
        $this->assertEquals('im last', $handlers[3]());
    }

    public function testGetSignalHandlersByPriorityEmpty()
    {
        $dispatcher = new Dispatcher;
        $this->assertEquals([], $dispatcher->getSignalHandlersByPriority('foo'));
    }

    public function testGetAllSignalHandlersEmpty()
    {
        $dispatcher = new Dispatcher;
        $this->assertEquals([], $dispatcher->getAllSignalHandlers());
    }

    public function testGetAllSignalHandlers()
    {
        $dispatcher = new Dispatcher;
        $dispatcher->register('test', [DispatcherTest::class, 'staticDummySignalHandler']);
        $dispatcher->register('test2', [DispatcherTest::class, 'staticDummySignalHandler']);

        $this->assertEquals([
            'test' => [0 => [0, [DispatcherTest::class, 'staticDummySignalHandler']]],
            'test2' => [1 => [0, [DispatcherTest::class, 'staticDummySignalHandler']]],
        ], $dispatcher->getAllSignalHandlers());
    }

    public function testUnregister()
    {
        $dispatcher = new Dispatcher;
        $dispatcher->register('test', [DispatcherTest::class, 'staticDummySignalHandler'], 1);
        $sid = $dispatcher->register('test', [DispatcherTest::class, 'staticDummySignalHandler'], 2);
        $dispatcher->register('test', [DispatcherTest::class, 'staticDummySignalHandler'], 3);

        $dispatcher->unregister('test', $sid);

        // now assert the priorites of the remaining handlers
        $testHandlers = $dispatcher->getAllSignalHandlers()['test'];
        $this->assertEquals([1, 3], array_column($testHandlers, 0));
    }

    public function testClear()
    {
        $dispatcher = new Dispatcher;
        $dispatcher->register('test1', [DispatcherTest::class, 'staticDummySignalHandler'], 1);
        $dispatcher->register('test1', [DispatcherTest::class, 'staticDummySignalHandler'], 2);
        $dispatcher->register('test2', [DispatcherTest::class, 'staticDummySignalHandler'], 3);

        $dispatcher->clear('test1');

        $this->assertEquals(['test2'], array_keys($dispatcher->getAllSignalHandlers()));
    }

    public function testClearAll()
    {
        $dispatcher = new Dispatcher;
        $dispatcher->register('test1', [DispatcherTest::class, 'staticDummySignalHandler'], 1);
        $dispatcher->register('test1', [DispatcherTest::class, 'staticDummySignalHandler'], 2);
        $dispatcher->register('test2', [DispatcherTest::class, 'staticDummySignalHandler'], 3);

        $dispatcher->clearAll();

        $this->assertEquals([], array_keys($dispatcher->getAllSignalHandlers()));
    }

    public function testDispatching()
    {
        $signal = new TestSignal;
        $dispatcher = new Dispatcher;

        $signal->data = 'foo';

        $dispatcher->register('test', function(TestSignal $signal) 
        {
            $this->assertEquals('foo', $signal->data);
            $signal->data = 'bar';
        });

        $dispatcher->dispatch('test', $signal);
        $this->assertEquals('bar', $signal->data);
    }

    public function testPropagation()
    {
        $signal = new TestSignal;
        $dispatcher = new Dispatcher;

        $signal->data = 0;

        $dispatcher->register('test', function(TestSignal $signal) { $signal->data++; });
        $dispatcher->register('test', function(TestSignal $signal) { $signal->data++; });
        $dispatcher->register('test', function(TestSignal $signal) { $signal->data++; $signal->stopPropagation(); });
        $dispatcher->register('test', function(TestSignal $signal) { $signal->data++; });

        $dispatcher->dispatch('test', $signal);

        $this->assertEquals(3, $signal->data);
    }

    public function testReadSignalsFromContainer()
    {
        $container = new Container;
        $container->bind('test.signal_handler', function(Container $container) { 
            return new class { 
                public function handle(TestSignal $signal) {
                    $signal->data = 'foo';
                }
            };
        });
        $container->addMetaData('test.signal_handler', 'on', ['phpunit', 'call' => 'handle']);

        $signal = new TestSignal;
        $dispatcher = new Dispatcher;

        $dispatcher->readSignalsFromContainer($container);
        $dispatcher->dispatch('phpunit', $signal);

        $this->assertEquals('foo', $signal->data);
    }

    public function testReadSignalsFromContainerMissingKey()
    {
        $this->expectException(\VISU\Signal\RegisterHandlerException::class);

        $container = new Container;
        $container->bind('test.signal_handler', function(Container $container) { 
            return new class { 
                public function handle(TestSignal $signal) {
                    $signal->data = 'foo';
                }
            };
        });
        $container->addMetaData('test.signal_handler', 'on', []);

        $dispatcher = new Dispatcher;
        $dispatcher->readSignalsFromContainer($container);
    }

    public function testReadSignalsFromContainerMissingCallName()
    {
        $this->expectException(\VISU\Signal\RegisterHandlerException::class);

        $container = new Container;
        $container->bind('test.signal_handler', function(Container $container) { 
            return new class { 
                public function handle(TestSignal $signal) {
                    $signal->data = 'foo';
                }
            };
        });
        $container->addMetaData('test.signal_handler', 'on', ['phpunit']);

        $dispatcher = new Dispatcher;
        $dispatcher->readSignalsFromContainer($container);
    }
}
