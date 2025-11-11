<?php 

namespace App\Tests\ECS;

use Exception;
use VISU\ECS\EntityRegistry;
use VISU\ECS\Exception\EntityRegistryException;

class EntityRegistryTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct() : void
    {
        $this->assertInstanceOf(EntityRegistry::class, new EntityRegistry);
    }

    public function testCreateAndValid() : void
    {
        $entites = new EntityRegistry();

        $id1 = $entites->create();
        $id2 = $entites->create();
        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotEquals($id1, $id2);

        $this->assertTrue($entites->valid($id1));
        $this->assertTrue($entites->valid($id2));
        $this->assertFalse($entites->valid(42));
    }

    public function testNotRegisterComponents() : void
    {
        $this->expectException(EntityRegistryException::class);

        $entites = new EntityRegistry();
        $entity = $entites->create();
        $entites->attach($entity, new \Error('test'));
    }

    public function testAttachAndDetach() : void
    {
        $entites = new EntityRegistry();
        $entites->registerComponent(\Error::class);

        $entity = $entites->create();

        $this->assertFalse($entites->has($entity, \Error::class));

        $entites->attach($entity, new \Error('test'));

        $this->assertTrue($entites->has($entity, \Error::class));
        $this->assertEquals('test', $entites->get($entity, \Error::class)->getMessage());

        $entites->detach($entity, \Error::class);

        $this->assertFalse($entites->has($entity, \Error::class));
    }

    public function testView() : void
    {
        $entites = new EntityRegistry();
        $entites->registerComponent(\Exception::class);
        $entites->registerComponent(\Error::class);

        $expectedErrorBuffer = [];
        $expectedExceptionBuffer = [];

        for($i = 0; $i < 50; $i++) {
            $entity = $entites->create();

            $errorMessage = 'er' . $i;
            $exceptionMessage = 'ex' . $i;

            $entites->attach($entity, new \Error($errorMessage));
            $entites->attach($entity, new \Exception($exceptionMessage));

            $expectedErrorBuffer[] = $errorMessage;
            $expectedExceptionBuffer[] = $exceptionMessage;
        }

        $actualErrorBuffer = [];
        $actualExceptionBuffer = [];

        foreach($entites->view(\Error::class) as $entity => $error) {
            $actualErrorBuffer[] = $error->getMessage();
        }

        foreach($entites->view(\Exception::class) as $entity => $exception) {
            $actualExceptionBuffer[] = $exception->getMessage();
        }

        $this->assertEquals($expectedErrorBuffer, $actualErrorBuffer);
        $this->assertEquals($expectedExceptionBuffer, $actualExceptionBuffer);
    }

    public function testListWith() : void
    {
        $entites = new EntityRegistry();
        $entites->registerComponent(\Exception::class);
        $entites->registerComponent(\Error::class);

        $e1 = $entites->create();
        $entites->attach($e1, new \Exception('e1'));

        $e2 = $entites->create();
        $entites->attach($e2, new \Exception('e2'));
        $entites->attach($e2, new \Error('e2'));

        $e3 = $entites->create();
        $entites->attach($e3, new \Error('e3'));
        $entites->attach($e3, new \Exception('e3'));

        $e4 = $entites->create();
        $entites->attach($e4, new \Error('e4'));

        $this->assertEquals([$e2, $e3], $entites->listWith(\Exception::class, \Error::class));
    }


    public function testSerialization() : void
    {
        $entites = new EntityRegistry();
        $entites->registerComponent(\Exception::class);
        $entites->registerComponent(\Error::class);

        $e1 = $entites->create();
        $entites->attach($e1, new \Exception('e1'));

        $e2 = $entites->create();
        $entites->attach($e2, new \Exception('e2'));
        $entites->attach($e2, new \Error('e2'));

        $buffer = $entites->serialize([\Exception::class, \Error::class]);

        $this->assertIsString($buffer);

        $entites = new EntityRegistry();
        $entites->deserialize($buffer);

        $this->assertTrue($entites->valid($e1));
        $this->assertTrue($entites->valid($e2));
        $this->assertEquals('e1', $entites->get($e1, \Exception::class)->getMessage());
        $this->assertEquals('e2', $entites->get($e2, \Exception::class)->getMessage());
        $this->assertEquals('e2', $entites->get($e2, \Error::class)->getMessage());
        $this->assertEquals(3, $entites->create());
    }

    public function testTryGet() : void
    {
        $entites = new EntityRegistry();
        $entites->registerComponent(\Exception::class);

        $e1 = $entites->create();
        $entites->attach($e1, new \Exception('e1'));

        $e2 = $entites->create();
        $entites->attach($e2, new \Exception('e2'));

        $this->assertEquals('e1', $entites->tryGet($e1, \Exception::class)->getMessage());
        $this->assertNull($entites->tryGet($e2, \Error::class));
    }

    public function testViewWith() : void
    {
        $entites = new EntityRegistry();
        $entites->registerComponent(\Exception::class);
        $entites->registerComponent(\Error::class);

        $e1 = $entites->create();
        $entites->attach($e1, new \Exception('e1'));

        $e2 = $entites->create();
        $entites->attach($e2, new \Exception('e2'));
        $entites->attach($e2, new \Error('e2'));

        $e3 = $entites->create();
        $entites->attach($e3, new \Error('e3'));
        $entites->attach($e3, new \Exception('e3'));

        $e4 = $entites->create();
        $entites->attach($e4, new \Error('e4'));

        $actualBuffer = [];
        foreach($entites->viewWith(\Exception::class, \Error::class) as $entity => $components) {
            $actualBuffer[] = $entity;
        }

        $this->assertEquals([$e2, $e3], $actualBuffer);
    }
}