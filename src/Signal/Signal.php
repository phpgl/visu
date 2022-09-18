<?php 

namespace VISU\Signal;

class Signal
{
    /**
     * Is this signal done? 
     * If this is true the signal wont be processed further after being set.
     * 
     * @var bool
     */
    private bool $stopPropagation = false;

    /**
     * Stop the propagation of the signal
     *
     * @return void
     */
    public function stopPropagation() 
    {
        $this->stopPropagation = true;
    }

    /**
     * Has the propagation been stopped?
     */
    public function isPropagationStopped() : bool
    {
        return $this->stopPropagation;
    }
}
