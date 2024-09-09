<?php 

namespace VISU\Runtime;

use VISU\Instrument\Clock;

class GameLoop
{
    /**
     *  The targeted amount of game update ticks per second of the game loop.
     * 
     * @var float
     */
    private float $targetTickRate;

    /**
     * The timestep in nanoseconds of a single game update tick.
     * 
     * @var int
     */
    private int $timestepNs;

    /**
     * The maximum amount of updates that can be executed, before 
     * a render call is forced.
     * 
     * @var int
     */
    private int $maxUpdatesPerFrame;

    /**
     * An array of the last few frametiems in nanoseconds.
     * This is used to calculate the average frametime.
     * 
     * @var array<int>
     */
    private array $frameTimeSamples = [];

    /**
     * The amount of samples that are collected to determine the average frametime.
     * 
     * @var int
     */
    public int $frameTimeSampleCount = 32;

    /**
     * An array of the last tick count per frame to calculate the average tick count.
     * 
     * @var array<int>
     */
    private array $tickCountSamples = [];

    /**
     * The amount of samples that are collected to determine the average tick count.
     * 
     * @var int
     */
    public int $tickCountSampleCount = 32;

    /**
     * An array of tick time samples in nanoseconds.
     * This represents the time it took to execute a single game update tick.
     * 
     * @var array<int>
     */
    private array $tickTimeSamples = [];

    /**
     * The amount of samples that are collected to determine the average tick time.
     * 
     * @var int
     */
    public int $tickTimeSampleCount = 60;

    /**
     * If set to true, the game loop will force stop, even if the delegate doesn't want to.
     */
    private bool $forceStop = false;

    /**
     * Constructor
     * 
     * @param GameLoopDelegate $delegate The game loop delegate to handle update, draw etc.
     * @param float $targetTickRate The targeted amount of game update ticks of the game loop.
     * @param int $maxUpdatesPerFrame The maximum amount of updates that can be executed, before a render call is forced.
     * 
     * @return void 
     */
    public function __construct(
        private GameLoopDelegate $delegate,
        float $targetTickRate = 60.0,
        int $maxUpdatesPerFrame = 10,
    ) {
        $this->targetTickRate = $targetTickRate;
        $this->maxUpdatesPerFrame = $maxUpdatesPerFrame;
        $this->timestepNs = (int) (1.0 / $targetTickRate * 1000000000);
    }

    /**
     * Returns the target tick rate of the game loop.
     */
    public function getTargetTickRate() : float
    {
        return $this->targetTickRate;
    }

    /**
     * Returns the maximum amount of updates that can be executed, before a render call is forced.
     */
    public function getMaxUpdatesPerFrame() : int
    {
        return $this->maxUpdatesPerFrame;
    }

    /**
     * Returns the timestep in nanoseconds of a single game update tick.
     */
    public function getTimestepNs() : int
    {
        return $this->timestepNs;
    }

    /**
     * Returns the average frametime in nanoseconds.
     * 
     * @return float 
     */
    public function getAverageFrameTime() : float
    {
        if (count($this->frameTimeSamples) === 0) {
            return 0.0;
        }

        return array_sum($this->frameTimeSamples) / count($this->frameTimeSamples);
    }

    /**
     * Returns the average frames per second.
     * 
     * @return float 
     */
    public function getAverageFps() : float
    {
        if (count($this->frameTimeSamples) === 0) {
            return 0.0;
        }

        return 1000000000.0 / $this->getAverageFrameTime();
    }

    /**
     * Returns the average tick count per frame.
     * 
     * @return float 
     */
    public function getAverageTickCount() : float
    {
        if (count($this->tickCountSamples) === 0) {
            return 0.0;
        }

        return array_sum($this->tickCountSamples) / count($this->tickCountSamples);
    }

    /**
     * Returns the average tick time in nanoseconds. 
     * This represents the time it took to execute a single game update tick.
     * 
     * @return float
     */
    public function getAverageTickTime() : float
    {
        if (count($this->tickTimeSamples) === 0) {
            return 0.0;
        }

        return array_sum($this->tickTimeSamples) / count($this->tickTimeSamples);
    }

    private function formatNStoHuman(int $ns) : string
    {
        if ($ns < 1000) {
            return sprintf("%.2f ns", $ns);
        }
        elseif ($ns < 1000000) {
            return sprintf("%.2f µs", $ns / 1000);
        }
        elseif ($ns < 1000000000) {
            return sprintf("%.2f ms", $ns / 1000000);
        }
        else {
            return sprintf("%.2f s", $ns / 1000000000);
        }
    }

    /**
     * Returns the average tick time in a human readable format.
     * 
     * @return string 
     */
    public function getAverageTickTimeFormatted() : string
    {
        return $this->formatNStoHuman((int) $this->getAverageTickTime());
    }

    /**
     * Returns the average frametime in a human readable format.
     * 
     * @return string 
     */
    public function getAverageFrameTimeFormatted() : string
    {
        return $this->formatNStoHuman((int) $this->getAverageFrameTime());
    }

    /**
     * Force stops the game loop.
     */
    public function forceStop() : void
    {
        $this->forceStop = true;
    }

    /**
     * Starts and runs the game loop
     * 
     * @return void 
     */
    public function start() : void
    {
        // current lag in nano seconds
        $lag = 0;

        // the start time of the last frame
        $lastFrameStart = Clock::now64();
        $frameTook = 0;
        $deltaTime = 0;
        $frameTickCount = 0;

        // loop forever until the delegate tells us to stop
        while (!$this->delegate->shouldStop() && !$this->forceStop) 
        {
            // determine delta time since last frame
            $deltaTime = Clock::now64() - $lastFrameStart;
            $lastFrameStart = Clock::now64();

            // add delta time to lag
            $lag += $deltaTime;

            $frameTickCount = 0;
            while ($lag >= $this->timestepNs && $frameTickCount < $this->maxUpdatesPerFrame) 
            {
                // subtract the timestep from the lag
                $lag -= $this->timestepNs;

                // update the game state
                $updateStart = Clock::now64();
                $this->delegate->update();
                $this->tickTimeSamples[] = Clock::now64() - $updateStart;
                if (count($this->tickTimeSamples) > $this->tickTimeSampleCount) {
                    array_shift($this->tickTimeSamples);
                }

                // increment the ticker
                $frameTickCount++;
            }

            // draw the frame
            $this->delegate->render($lag / $this->timestepNs);

            // store how many nanoseconds the frame took
            $frameTook = Clock::now64() - $lastFrameStart;
            $this->frameTimeSamples[] = $frameTook;
            if (count($this->frameTimeSamples) > $this->frameTimeSampleCount) {
                array_shift($this->frameTimeSamples);
            }

            // store the current tick count per frame
            $this->tickCountSamples[] = $frameTickCount;
            if (count($this->tickCountSamples) > $this->tickCountSampleCount) {
                array_shift($this->tickCountSamples);
            }
        }
    }

}