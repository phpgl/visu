<?php

namespace VISU\Graphics\Rendering\Renderer\TextLabelRenderer;

use GL\Math\Vec4;
use VISU\Geo\Transform;
use VISU\Graphics\BasicVertexArray;

class TextLabelRenderGroup
{
    /**
     * The Text labels inside this group
     * 
     * @var array<TextLabel>
     */
    private array $labels = [];

    /**
     * Constructor
     */
    public function __construct(
        /**
         * The font handle of this group.
         */
        public readonly string $fontHandle,

        /**
         * Simple vertex array to store the transformed character vertices of the entire group.
         */
        public readonly BasicVertexArray $vertexArray
    )
    {
    }

    /**
     * Adds a text label to this group.
     */
    public function addLabel(TextLabel $label) : void
    {
        $this->labels[] = $label;
    }

    /**
     * Returns the text labels in this group.
     * 
     * @return array<TextLabel>
     */
    public function getLabels() : array
    {
        return $this->labels;
    }

    /**
     * Removes a given text label from this group.
     */
    public function removeLabel(TextLabel $label) : void
    {
        $index = array_search($label, $this->labels, true);

        if ($index !== false) {
            unset($this->labels[$index]);
        }
    }

    /**
     * Removes all text labels from this group.
     */
    public function clear() : void
    {
        $this->labels = [];
    }

    /**
     * Requires rebuild
     * Returns true if any of the labels in this group requires a rebuild.
     */
    public function requiresRebuild() : bool
    {
        foreach ($this->labels as $label) {
            if ($label->isDirty()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark all labels as clean.
     * Keep in mind that the transforms only become clean if the matrix is fetched
     */
    public function markClean() : void
    {
        foreach ($this->labels as $label) {
            $label->markClean();
        }
    }
}