<?php

namespace VISU\Tests\Command;

use PHPUnit\Framework\TestCase;
use VISU\Command\TranspileCommand;
use VISU\ECS\ComponentRegistry;

class TranspileCommandTest extends TestCase
{
    public function testToClassNameConvertsPascalCase(): void
    {
        $registry = new ComponentRegistry();
        $command = new TranspileCommand($registry);

        // Use reflection to test the private method
        $ref = new \ReflectionMethod($command, 'toClassName');
        $ref->setAccessible(true);

        $this->assertSame('OfficeLevel1', $ref->invoke($command, 'office_level1'));
        $this->assertSame('Hud', $ref->invoke($command, 'hud'));
        $this->assertSame('MainMenu', $ref->invoke($command, 'main-menu'));
        $this->assertSame('MyScene', $ref->invoke($command, 'my scene'));
        $this->assertSame('Test123Scene', $ref->invoke($command, 'test123_scene'));
    }

    public function testCommandHasDescription(): void
    {
        $registry = new ComponentRegistry();
        $command = new TranspileCommand($registry);

        $this->assertNotEmpty($command->getCommandShortDescription());
    }

    public function testCommandHasExpectedArguments(): void
    {
        $registry = new ComponentRegistry();
        $command = new TranspileCommand($registry);

        $args = $command->getExpectedArguments([]);
        $this->assertArrayHasKey('force', $args);
        $this->assertArrayHasKey('scenes', $args);
        $this->assertArrayHasKey('ui', $args);
        $this->assertArrayHasKey('prefabs', $args);
        $this->assertArrayHasKey('output', $args);
    }
}
