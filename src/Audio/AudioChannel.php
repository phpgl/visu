<?php

namespace VISU\Audio;

enum AudioChannel: string
{
    case SFX = 'sfx';
    case Music = 'music';
    case UI = 'ui';
    case Ambient = 'ambient';
}
