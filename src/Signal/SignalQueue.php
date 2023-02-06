<?php 

namespace VISU\Signal;

/**
 * @template T of Signal
 */
class SignalQueue
{
    /**
     * An array of queued signals
     * 
     * @var array<T>
     */
    protected array $signals = [];

    /**
     * Constructor
     */
    public function __construct(
        public readonly string $signalKey,
        public readonly int $handlerId,
    ) {

    }

    /**
     * Pushes a signal to the queue
     * 
     * @param T $signal
     */
    public function push(Signal $signal) : void
    {
        $this->signals[] = $signal;
    }

    /**
     * Returns the first signal in the queue and removes it from the queue
     * 
     * @return T|null
     */
    public function shift() : ?Signal
    {
        return array_shift($this->signals);
    }

    /**
     * Returns the last signal in the queue and removes it from the queue
     * 
     * @return T|null
     */
    public function pop() : ?Signal
    {
        return array_pop($this->signals);
    }

    /**
     * Returns an iterator returning all signals in the queue and removing them
     * 
     * @return \Generator<T>
     */
    public function poll() : \Generator
    {
        while ($signal = array_shift($this->signals)) {
            yield $signal;
        }
    }

    /**
     * Flushes the queue, removing all signals
     */
    public function flush() : void
    {
        $this->signals = [];
    }

    /**
     * Size of the queue, i.e. the number of signals in the queue
     */
    public function size() : int
    {
        return count($this->signals);
    }
}
