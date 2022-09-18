<?php 

namespace VISU\Signals;

use Exception;

trait ExceptionSignalTrait
{
    /**
     * Exception instance 
     *
     * @var Exception|null
     */
    private ?Exception $exception = null;

    /**
     * Does the signal have a exception
     *
     * @return bool
     */
    public function hasException() : bool
    {
        return !is_null($this->exception);
    }

    /**
     * Set the exception for the signal
     *
     * @param Exception                 $exception
     * @return void
     */
    public function setException(Exception $exception)
    {
        $this->exception = $exception;
    }

    /**
     * Returns the current exception or null
     *
     * @return Exception|null
     */
    public function getException() : ?Exception
    {
        return $this->exception;
    }
}
