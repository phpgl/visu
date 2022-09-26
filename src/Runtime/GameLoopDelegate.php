<?php 

namespace VISU\Runtime;

interface GameLoopDelegate
{
    /**
     * Update the games state
     * This method might be called multiple times per frame, or not at all if
     * the frame rate is very high.
     * 
     * The update method should step the game forward in time, this is the place
     * where you would update the position of your game objects, check for collisions
     * and so on. 
     * 
     * @return void 
     */
    public function update() : void;

    /**
     * Render the current game state
     * This method is called once per frame.
     * 
     * The render method should draw the current game state to the screen. You recieve 
     * a delta time value which you can use to interpolate between the current and the
     * previous frame. This is useful for animations and other things that should be
     * smooth with variable frame rates.
     * 
     * @param float $deltaTime
     * @return void 
     */
    public function render(float $deltaTime) : void;

    /**
     * Loop should stop
     * This method is called once per frame and should return true if the game loop
     * should stop. This is useful if you want to quit the game after a certain amount
     * of time or if the player has lost all his lives etc..
     * 
     * @return bool 
     */
    public function shouldStop() : bool;
}