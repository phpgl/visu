<?php

namespace VISU\Tests\Build;

use PHPUnit\Framework\TestCase;
use VISU\Command\BuildCommand;

class BuildCommandTest extends TestCase
{
    public function testCommandHasDescription(): void
    {
        $command = new BuildCommand();
        $this->assertNotEmpty($command->getCommandShortDescription());
    }

    public function testCommandHasExpectedArguments(): void
    {
        $command = new BuildCommand();
        $args = $command->getExpectedArguments([]);

        $this->assertArrayHasKey('platform', $args);
        $this->assertArrayHasKey('dry-run', $args);
        $this->assertArrayHasKey('micro-sfx', $args);
        $this->assertArrayHasKey('output', $args);
    }
}
