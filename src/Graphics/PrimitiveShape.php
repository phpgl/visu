<?php

namespace VISU\Graphics;

enum PrimitiveShape: string
{
    case cube = 'cube';
    case sphere = 'sphere';
    case plane = 'plane';
    case cylinder = 'cylinder';
    case capsule = 'capsule';
    case quad = 'quad';
    case cone = 'cone';
    case torus = 'torus';

    /**
     * Returns the model identifier used in ModelCollection
     */
    public function modelId(): string
    {
        return '__primitive_' . $this->value;
    }
}
