<?php 

namespace VISU\Signals;

use VISU\Signal\Signal;

class AnySignal extends Signal
{
    /**
     * Data payload
     *
     * @var mixed
     */
    private $data = null;

    /**
     * Constructor
     *
     * @param mixed             $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Set the data for the signal
     *
     * @param mixed               $data
     * @return void
     */
    public function setData(mixed $data)
    {
        $this->data = $data;
    }

    /**
     * Returns the current data or null
     *
     * @return mixed|null
     */
    public function getData() : mixed
    {
        return $this->data;
    }
}
