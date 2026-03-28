<?php

namespace VISU\Signals\Locale;

use VISU\Signal\Signal;

class LocaleChangedSignal extends Signal
{
    public function __construct(
        public readonly string $previousLocale,
        public readonly string $newLocale,
    ) {
    }
}
