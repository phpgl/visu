<?php 

namespace VISU\Instrument;

/**
 * A simple wrapper around the high resolution clock 
 * to hold a nano second timestamp.
 */
class ClockNanoseconds
{
    public function __construct(
        public int $seconds,
        public int $nanoseconds,
    ) {}
    
    /**
     * Adds the given amount of nanoseconds as a single 64 bit integer to this timestamp.
     * 
     * @param int $nanoseconds
     * 
     * @return void
     */
    public function add64(int $nanoseconds) : void
    {
        $this->seconds += $nanoseconds / 1000000000;
        $this->nanoseconds += $nanoseconds % 1000000000;
    }

    /**
     * Adds the given amount of nanoseconds to this timestamp.
     * 
     * @param ClockNanoseconds      $other 
     * 
     * @return self 
     */
    public function add(ClockNanoseconds $other) : self
    {
        $this->seconds += $other->seconds;
        $this->nanoseconds += $other->nanoseconds;
        
        if ($this->nanoseconds >= 1000000000) {
            $this->seconds += 1;
            $this->nanoseconds -= 1000000000;
        }

        return $this;
    }

    /**
     * Subtracts the given amount of nanoseconds from this timestamp.
     * 
     * @param ClockNanoseconds      $other 
     * 
     * @return self 
     */
    public function sub(ClockNanoseconds $other) : self
    {
        $this->seconds -= $other->seconds;
        $this->nanoseconds -= $other->nanoseconds;
        
        if ($this->nanoseconds < 0) {
            $this->seconds -= 1;
            $this->nanoseconds += 1000000000;
        }

        return $this;
    }

    /**
     * Returns the current nanoseconds timestamp as a single 64 bit integer.
     * 
     * @return int 
     */
    public function int64() : int
    {
        return $this->seconds * 1000000000 + $this->nanoseconds;
    }
}