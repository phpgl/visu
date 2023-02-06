<?php 

namespace VISU\Signal;

use ClanCats\Container\Container;

use VISU\Signal\RegisterHandlerException;

class Dispatcher implements DispatcherInterface
{
    /**
     * Registered signals
     *
     * @var array<string, array<array{int, callable}>>
     */
    protected array $signalHandlers = [];

    /**
     * Internal handler ID counter
     * 
     * @var int
     */
    private int $handlerId = 0;

    /**
     * Register available signal handlers from the given container
     *
     * @param Container             $container
     * @return void
     */
    public function readSignalsFromContainer(Container $container) 
    {
        foreach($container->serviceNamesWithMetaData('on') as $serviceName => $signalHandlerMetaData)
        {
            // a action can have multiple routes handle all of them
            foreach($signalHandlerMetaData as $singalHandler)
            {
                if (!is_string($singalHandler[0] ?? false)) {
                    throw new RegisterHandlerException('The signal handler event key must be a string.');
                }

                if (!isset($singalHandler['call']) || !is_string($singalHandler['call'])) {
                    throw new RegisterHandlerException('You must define the name of the function you would like to call.');
                }

                $priority = $singalHandler['priority'] ?? 0;

                // register the signal handler
                $this->register($singalHandler[0], function(Signal $signal) use($container, $singalHandler, $serviceName) {
                    $container->get($serviceName)->{$singalHandler['call']}($signal);
                }, $priority);
            }
        }
    }

    /**
     * Register a signal handler
     * You can pass a priority to define the order in which the handlers are called.
     * The higher the priority the earlier the handler is called.
     * 
     * When passing a ID, you are responsible for ensuring that the ID is unique.
     * This can also be a handy tool if you want a handler to be only registered once for a specifc use case.
     *
     * @param string        $key
     * @param callable      $handler
     * @param int           $priority
     * @param int|null      $id (optional) The ID of the handler, this acts as an identifier for later.
     * 
     * @return int The handler registration ID, store this int if you want to remove the handler later.
     */
    public function register(string $key, callable $handler, int $priority = 0, ?int $id = null) : int
    {
        if (!isset($this->signalHandlers[$key])) {
            $this->signalHandlers[$key] = [];
        }

        if ($id === null) {
            $id = $this->handlerId++;
        }

        $this->signalHandlers[$key][$id] = [$priority, $handler];

        return $id;
    }

    /**
     * Creates a signal queue for the given key
     * A signal queue is a special signal handler, that aggregates the recieved signals 
     * until the user pops them from the queue, or the queue is flushed.
     * 
     * Don't forget to destroy the queue with `destroySignalQueue` when you are done with it.
     * 
     * @param string        $key
     * @param int           $priority
     * @return SignalQueue
     */
    public function createSignalQueue(string $key, int $priority = 0) : SignalQueue
    {
        $queue = new SignalQueue($key, $this->handlerId++);

        $this->register($key, function(Signal $signal) use($queue) {
            $queue->push($signal);
        }, $priority, $queue->handlerId);

        return $queue;
    }

    /**
     * Creates a limited signal queue for the given key
     * This is the same as `createSignalQueue` but the queue is limited to the given size, and drops all signals after that.
     * 
     * Don't forget to destroy the queue with `destroySignalQueue` when you are done with it.
     * 
     * @param string        $key
     * @param int           $size The maximum size of the queue
     * @param int           $priority
     * @return SignalQueue
     */
    public function createLimitedSignalQueue(string $key, int $size = 1024, int $priority = 0) : SignalQueue
    {
        $queue = new SignalQueueLimited($key, $this->handlerId++);
        $queue->maxSignals = $size;

        $this->register($key, function(Signal $signal) use($queue, $size) {
            $queue->push($signal);
        }, $priority, $queue->handlerId);

        return $queue;
    }

    /**
     * Desroys a signal queue and removes the handler bound to it.
     * 
     * @param SignalQueue $queue
     * @return void
     */
    public function destroySignalQueue(SignalQueue $queue) : void
    {
        $this->unregister($queue->signalKey, $queue->handlerId);
    }

    /**
     * Remove a signal handler
     *
     * @param string        $key
     * @param int           $id
     * @return void
     */
    public function unregister(string $key, int $id) : void
    {
        if (isset($this->signalHandlers[$key][$id])) {
            unset($this->signalHandlers[$key][$id]);
        }
    }

    /**
     * Clears all handlers from a key
     * 
     * @param string        $key
     * @return void
     */
    public function clear(string $key) : void
    {
        if (isset($this->signalHandlers[$key])) {
            unset($this->signalHandlers[$key]);
        }
    }

    /**
     * Clears all signal handlers from the dispatcher
     * This will remove all registered keys
     * 
     * @return void
     */
    public function clearAll() : void
    {
        $this->signalHandlers = [];
    }


    /**
     * Get all registered signal handlers
     *
     * @return array<string, array<array{int, callable}>>
     */
    public function getAllSignalHandlers() : array 
    {
        return $this->signalHandlers;
    }

    /**
     * Get the signal handlers with the given key and sort them by priority
     *
     * @param string            $key
     * @return array<callable>
     */
    public function getSignalHandlersByPriority(string $key) : array
    {
        if (!isset($this->signalHandlers[$key])) {
            return [];
        }

        $handlers = $this->signalHandlers[$key];

        usort($handlers, function($a, $b) {
            return $a[0] <=> $b[0];
        });

        return array_column($handlers, 1);
    }

    /**
     * Dispatch a given signal to all handlers
     * Calling this method will invoke all handlers that are registered to the given signal key.
     *
     * @param string        $key The signal key, this is name on which handlers are registered
     * @param Signal        $signal The signal to dispatch
     * @return void
     */
    public function dispatch(string $key, Signal $signal) : void
    {
        foreach ($this->getSignalHandlersByPriority($key) as $handler) 
        {
            call_user_func_array($handler, [$signal]);

            // exit if propagation has been stopped
            if ($signal->isPropagationStopped()) {
                break;
            }
        }
    }
}
