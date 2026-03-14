<?php

namespace VISU\UI;

enum UINodeType: string
{
    case Panel = 'panel';
    case Label = 'label';
    case Button = 'button';
    case ProgressBar = 'progressbar';
    case Checkbox = 'checkbox';
    case Select = 'select';
    case Image = 'image';
    case Space = 'space';
}
