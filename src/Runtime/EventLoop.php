<?php 

namespace VISU\Runtime;

use Closure;
use VISU\Exception\VISUException;

class EventLoop
{
    /**
     * The main event loop instance
     */
    private static ?EventLoop $instance = null;

    /**
     * Returns the main event loop
     */
    public static function main() : EventLoop
    {
        if (self::$instance === null) {
            throw new VISUException('No main event loop registered, please register one using EventLoop::registerMain()');
        }
        
        return self::$instance;
    }

    /**
     * Returns boolean if a static main event loop is registered
     */
    public static function hasMain() : bool
    {
        return self::$instance !== null;
    }

    /**
     * Registers the main event loop instance
     */
    public static function registerMain(EventLoop $loop) : void
    {
        self::$instance = $loop;
    }

    /**
     * Defer a callback to run after $delay milliseconds
     * Returns a cancel function
     * 
     * @param callable $callback The callback to execute
     * @param int $delay The delay in milliseconds
     * @return callable Cancel function
     */
    public static function defer(int $delay, callable $callback) : callable
    {
        return self::main()->schedule($delay, $callback);
    }

    /** 
     * @var TimerHeap Min-heap of scheduled timers ordered by execution time
     */
    private TimerHeap $timers;
    
    /** 
     * @var array<int, Timer> Map of timer IDs to Timer objects for O(1) cancellation lookup
     */
    private array $timerMap = [];
    
    private int $timerIdCounter = 0;
    
    /**
     * Cached next timer execution time for early termination optimization
     */
    private ?int $nextTimerTime = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->timers = new TimerHeap();
    }

    /**
     * Creates the internal timer and returns a cancel function
     */
    public function schedule(int $delay, callable $callback) : callable
    {
        $id = ++$this->timerIdCounter;
        $targetTime = $this->now() + max(0, $delay);

        // convert callable
        $closureCallback = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);
        $timer = new Timer($targetTime, $closureCallback, $id);
        
        // add to heap 
        $this->timers->insert($timer);
        
        // maintain map 
        $this->timerMap[$id] = $timer;
        
        // update cached next timer time
        if ($this->nextTimerTime === null || $targetTime < $this->nextTimerTime) {
            $this->nextTimerTime = $targetTime;
        }

        // return cancel function
        return function () use ($id) {
            if (isset($this->timerMap[$id])) {
                $timer = $this->timerMap[$id];
                $timer->cancel();
                
                // invalidate cache if we're removing the next timer
                if ($this->nextTimerTime === $timer->time) {
                    $this->nextTimerTime = null;
                }
                
                unset($this->timerMap[$id]);
            }
        };
    }

    /**
     * Ticker, call this in your game loop.
     * 
     * This will be where the scheduled timers are checked and executed.
     * Optimized with early termination and heap-based processing.
     */
    public function tick(): void
    {
        // early termination: if no timers or next timer isn't ready yet
        if ($this->timers->count() === 0) {
            return;
        }
        
        $now = $this->now();
        
        // early termination using cached next timer time
        if ($this->nextTimerTime !== null && $now < $this->nextTimerTime) {
            return;
        }
        
        // process ready timers from heap (already sorted by time)
        while ($this->timers->count() > 0) {
            $timer = $this->timers->top();
            
            // early termination: if next timer isn't ready, we're done
            if ($timer->time > $now) {
                $this->nextTimerTime = $timer->time;
                break;
            }
            
            // remove from heap
            $this->timers->extract();
            
            // skip cancelled timers
            if ($timer->isCancelled()) {
                continue;
            }
            
            // remove from map
            unset($this->timerMap[$timer->id]);
            
            // execute the callback
            ($timer->callback)();
        }
        
        // update next timer cache
        $this->nextTimerTime = $this->timers->count() > 0 ? $this->timers->top()->time : null;
    }

    private function now(): int
    {
        return (int)(glfwGetTime() * 1000); // convert seconds to milliseconds
    }
}