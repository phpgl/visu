<?php

namespace VISU\Component;

use GL\Math\Mat4;
use VISU\Graphics\Animation\AnimationClip;
use VISU\Graphics\Animation\Skeleton;

class SkeletalAnimationComponent
{
    /**
     * The skeleton this entity uses
     */
    public ?Skeleton $skeleton = null;

    /**
     * Available animation clips
     * @var array<string, AnimationClip>
     */
    public array $clips = [];

    /**
     * Currently playing animation clip name (null = no animation)
     */
    public ?string $currentClip = null;

    /**
     * Current playback time in seconds
     */
    public float $time = 0.0;

    /**
     * Playback speed multiplier
     */
    public float $speed = 1.0;

    /**
     * Whether the animation loops
     */
    public bool $looping = true;

    /**
     * Whether the animation is playing
     */
    public bool $playing = true;

    /**
     * Computed bone matrices for the current frame (model-space).
     * These are uploaded to the shader as uniform array.
     * @var array<Mat4>
     */
    public array $boneMatrices = [];

    /**
     * Maximum number of bones supported by the shader
     */
    public const MAX_BONES = 128;

    /**
     * Adds an animation clip
     */
    public function addClip(AnimationClip $clip): void
    {
        $this->clips[$clip->name] = $clip;
    }

    /**
     * Starts playing an animation by name
     */
    public function play(string $clipName, bool $restart = true): void
    {
        if (!isset($this->clips[$clipName])) {
            return;
        }
        $this->currentClip = $clipName;
        $this->playing = true;
        if ($restart) {
            $this->time = 0.0;
        }
    }

    /**
     * Returns the currently active clip, or null
     */
    public function getActiveClip(): ?AnimationClip
    {
        if ($this->currentClip === null) {
            return null;
        }
        return $this->clips[$this->currentClip] ?? null;
    }
}
