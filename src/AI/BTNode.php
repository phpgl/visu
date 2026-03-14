<?php

namespace VISU\AI;

abstract class BTNode
{
    abstract public function tick(BTContext $context): BTStatus;

    public function reset(): void
    {
    }
}
