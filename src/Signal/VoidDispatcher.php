<?php 

namespace VISU\Signal;

/**
 * Dispatches all recieved signals into a deep empty void,
 * helpful for testing purposes
 */
class VoidDispatcher implements DispatcherInterface
{
    /**
     * Dispatch a given signal to all handlers
     * Calling this method will invoke all handlers that are registered to the given signal key.
     *
     * @param string        $key The signal key, this is name on which handlers are registered
     * @param Signal        $signal The signal to dispatch
     * @return void
     */
    public function dispatch(string $key, Signal $signal) : void
    {}
}