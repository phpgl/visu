<?php 

namespace VISU\Signal;

use ClanCats\Container\Container;

use VISU\Signal\RegisterHandlerException;

class Dispatcher
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
     * Dispatch a signal
     *
     * @param string        $key
     * @param Signal        $signal
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
