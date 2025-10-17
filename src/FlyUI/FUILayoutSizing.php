<?php

namespace VISU\FlyUI;

enum FUILayoutSizing 
{
    /**
     * Fill the available space
     */
    case fill;

    /**
     * Size to fit the content
     */
    case fit;

    /**
     * Fixed size in effective pixels
     */
    case fixed;
}