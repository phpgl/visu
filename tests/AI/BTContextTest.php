<?php

namespace Tests\AI;

use PHPUnit\Framework\TestCase;
use VISU\AI\BTContext;

class BTContextTest extends TestCase
{
    public function testBlackboardGetSet(): void
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        $ctx = new BTContext(42, $entities, 0.016);

        $this->assertNull($ctx->get('missing'));
        $this->assertEquals('default', $ctx->get('missing', 'default'));
        $this->assertFalse($ctx->has('key'));

        $ctx->set('key', 123);
        $this->assertTrue($ctx->has('key'));
        $this->assertEquals(123, $ctx->get('key'));
    }

    public function testEntityAndDeltaTime(): void
    {
        $entities = $this->createMock(\VISU\ECS\EntitiesInterface::class);
        $ctx = new BTContext(7, $entities, 0.033);

        $this->assertEquals(7, $ctx->entity);
        $this->assertEqualsWithDelta(0.033, $ctx->deltaTime, 0.001);
        $this->assertSame($entities, $ctx->entities);
    }
}
