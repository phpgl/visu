<?php

namespace VISU\Audio;

use GL\Audio\Engine;
use VISU\Audio\Exception\MusicPlayerException;
use VISU\OS\Logger;
use VISU\Runtime\EventLoop;

class MusicPlayer
{
    /**
     * The time in seconds to fade between tracks
     */
    public float $fadeDuration = 2.0;

    /**
     * The tracks available in the music player
     * 
     * @var array<string, MusicPlayerTrack>
     */
    private array $tracks = [];

    /**
     * Currently playing track
     */
    private ?MusicPlayerTrack $currentTrack = null;

    /**
     * Next track to be played (after the current one ends)
     */
    private ?MusicPlayerTrack $nextTrack = null;

    /**
     * Boolean if in the process of switching tracks
     */
    private bool $isSwitchingTracks = false;

    /**
     * Constructor 
     */
    public function __construct(
        private Engine $engine
    )
    {
        // sanity check that we have a main event loop as we rely on it
        if (!EventLoop::hasMain()) {
            throw new MusicPlayerException('MusicPlayer requires a main event loop to be registered before instantiation');
        }   
    }

    /**
     * Returns the active audio engine
     */
    public function getEngine() : Engine
    {
        return $this->engine;
    }

    /**
     * Instantly loads and plays the track with the given name
     */
    public function play(string $name) : void
    {
        if (!isset($this->tracks[$name])) {
            throw new MusicPlayerException("Track with ID '$name' does not exist in music player");
        }

        $track = $this->tracks[$name];
        $track->load($this->engine);

        // stop current track if playing
        if ($this->currentTrack !== null) {
            $this->currentTrack->getSound()?->stop();
        }

        // play the new track
        $track->getSound()?->play();
        $this->currentTrack = $track;
    }

    /**
     * Switches to the track with the given name
     * 
     * Will fade the current track out and fade the new track in
     */
    public function switchTo(string $name) : void
    {
        if (!isset($this->tracks[$name])) {
            throw new MusicPlayerException("Track with ID '$name' does not exist in music player");
        }

        // if nothing is playing yet just play the track
        if (!$this->currentTrack) {
            $this->play($name);
            return;
        }

        if ($this->isSwitchingTracks) {
            Logger::warn(sprintf('[MusicPlayer] Already switching tracks, ignoring request to switch to \'%s\'', $name));    
            return;
        }

        $this->isSwitchingTracks = true;

        $this->nextTrack = $this->tracks[$name];
        $this->nextTrack->load($this->engine);

        if ($currentSound = $this->currentTrack->getSound()) {
            $currentSound->fadeOut($this->fadeDuration);

            if ($nextSound = $this->nextTrack->getSound()) {
                $nextSound->play();
                $nextSound->setFade(0, 1, $this->fadeDuration);
            }

            EventLoop::defer((int)($this->fadeDuration * 1000), function() {
                $this->currentTrack?->unload();
                $this->currentTrack = $this->nextTrack;
                $this->nextTrack = null;

                $this->isSwitchingTracks = false;
            });
        }
    }

    /**
     * Converts a filepath to a track name
     * 
     * We consider the track name the basic filename without extension
     */
    private function filepathToTrackName(string $path, ?string $basePath = null) : string
    {
        if (is_null($basePath)) {
            $basename = basename($path);
        } else {
            $basename = ltrim(str_replace($basePath, '', $path), '/\\');
        }
        $dotPos = strrpos($basename, '.');
        if ($dotPos !== false) {
            return substr($basename, 0, $dotPos);
        }
        
        return $basename;
    }

    /**
     * Binds a given track to music player
     */
    public function bindTrack(string $name, MusicPlayerTrack $track) : void
    {
        // double check the file is loadable and exists
        if (!file_exists($track->path) || !is_readable($track->path)) {
            throw new MusicPlayerException("Audio file '{$track->path}' does not exist or is not readable");
        }

        if (isset($this->tracks[$name])) {
            throw new MusicPlayerException("Track with ID '$name' already exists in music player");
        }

        $this->tracks[$name] = $track;
    }

    /**
     * Adds a track to the music player using the given path
     */
    public function addTrack(string $path, ?string $basePath = null) : MusicPlayerTrack
    {
        $track = new MusicPlayerTrack($path);
        $this->bindTrack($this->filepathToTrackName($path, $basePath), $track);
        return $track;
    }

    /**
     * Recursively scans a directory and adds all audio files as tracks
     * 
     * @param array<string> $extensions Supported audio file extensions
     * @return array<MusicPlayerTrack>
     */
    public function addTracksFromDirectory(string $directory, array $extensions = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a']) : array
    {
        if (!is_dir($directory)) {
            throw new MusicPlayerException("Directory '$directory' does not exist or is not a directory");
        }

        if (!is_readable($directory)) {
            throw new MusicPlayerException("Directory '$directory' is not readable");
        }

        $addedTracks = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $extensions = array_map('mb_strtolower', $extensions);

        foreach ($iterator as $file) 
        {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) 
            {
                $extension = mb_strtolower($file->getExtension());
                
                // check if the file has a supported audio extension
                if (in_array($extension, $extensions)) {
                    $this->addTrack($file->getRealPath(), $directory);
                }
            }
        }

        return $addedTracks;
    }
}