<?php 

namespace VISU\Runtime;

class GameLoop
{
    /**
     * The target frame rate of the game loop.
     * 
     * @var float
     */
    private float $targetFrameRate;

    /**
     * The maximum amount of frames that can be skipped, 
     * before a render call is forced.
     * 
     * @var int
     */
    private int $maxFrameSkip;

    /**
     * Constructor
     * 
     * @param GameLoopDelegate $delegate The game loop delegate to handle update, draw etc.
     * @param float $targetFrameRate The target frame rate of the game loop.
     * @param int $maxUpdatesPerFrame The maximum amount of updates that can be skipped, before a render call is forced.
     * 
     * @return void 
     */
    public function __construct(
        private GameLoopDelegate $delegate,
        float $targetFrameRate = 60.0,
        int $maxUpdatesPerFrame = 10,
    ) {
        $this->targetFrameRate = $targetFrameRate;
        $this->maxFrameSkip = $maxUpdatesPerFrame;
    }

    /**
     * Starts and runs the game loop
     * 
     * @return void 
     */
    public function start() : void
    {
        $nextGameTick = microtime(true);
        $skipTicks = 1 / $this->targetFrameRate;
        $loops = 0;

        while (!$this->delegate->shouldStop()) 
        {
            $loops = 0;

            while (microtime(true) > $nextGameTick && $loops < $this->maxFrameSkip) 
            {
                $this->delegate->update();
                $nextGameTick += $skipTicks;
                $loops++;
            }

            $this->delegate->render($nextGameTick - microtime(true));
        }
    }

}