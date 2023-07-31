<?php 

namespace VISU\Signal;

/**
 * The same as SignalQueue, but with a maximum number of signals to queue
 * 
 * @template T of Signal
 * @extends SignalQueue<T>
 */
class SignalQueueLimited extends SignalQueue
{
    /**
     * The maximum number of signals to queue
     * 
     * @var int
     */
    public int $maxSignals = 1024;

    /**
     * Pushes a signal to the queue
     */
    public function push(Signal $signal) : void
    {
        if (count($this->signals) >= $this->maxSignals) {
            return;
        }

        parent::push($signal);
    }
}
