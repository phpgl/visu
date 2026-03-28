<?php

namespace VISU\AI;

interface StateInterface
{
    public function getName(): string;

    public function onEnter(BTContext $context): void;

    public function onUpdate(BTContext $context): void;

    public function onExit(BTContext $context): void;
}
