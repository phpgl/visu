<?php

namespace VISU\Component;

enum ParticleEmitterShape: string
{
    case Point = 'point';
    case Sphere = 'sphere';
    case Cone = 'cone';
    case Box = 'box';
}
