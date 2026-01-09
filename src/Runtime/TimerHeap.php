<?php

namespace VISU\Runtime;

use SplMinHeap;

/**
 * @extends SplMinHeap<Timer>
 */
class TimerHeap extends SplMinHeap
{   
    protected function compare(mixed $timer1, mixed $timer2): int
    {
        return $timer2->time <=> $timer1->time;
    }
}