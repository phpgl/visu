<?php

namespace VISU\Audio;

use GL\Audio\Engine;
use GL\Audio\Sound;
use VISU\OS\Logger;

class MusicPlayerTrack
{
    /**
     * Sound instance if loaded
     */
    private ?Sound $sound = null;

    /**
     * Constructor
     */
    public function __construct(
        /**
         * The path to the audio file
         */
        public readonly string $path
    )
    {
    }

    /**
     * Returns the loaded sound, or null if not loaded
     */
    public function getSound() : ?Sound
    {
        return $this->sound;
    }

    /**
     * Loads the sound file from disk
     */
    public function load(Engine $engine) : void
    {
        if ($this->sound === null) {
            Logger::info(sprintf('[MusicPlayer] Loading track \'%s\'', basename($this->path)));
            $this->sound = $engine->soundFromDisk($this->path);
        }
    }

    /**
     * Unloads the sound from memory
     */
    public function unload() : void
    {
        if ($this->sound !== null) {
            Logger::info(sprintf('[MusicPlayer] Unloading track \'%s\'', basename($this->path)));
            $this->sound = null;
        }
    }
}