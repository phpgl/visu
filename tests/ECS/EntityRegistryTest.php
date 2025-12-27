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

    public function testOnAttachListener() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);

        $attachedEntities = [];
        $attachedComponents = [];

        // register an attach listener
        $handle = $entities->onAttach(\Exception::class, function(int $entity, \Exception $component) use (&$attachedEntities, &$attachedComponents) {
            $attachedEntities[] = $entity;
            $attachedComponents[] = $component->getMessage();
        });

        $this->assertIsInt($handle);

        // create entities and attach components
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('e1'));

        $e2 = $entities->create();
        $entities->attach($e2, new \Exception('e2'));

        // verify the listener was called
        $this->assertEquals([$e1, $e2], $attachedEntities);
        $this->assertEquals(['e1', 'e2'], $attachedComponents);
    }

    public function testOnDetachListener() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);

        $detachedEntities = [];
        $detachedComponents = [];

        // register a detach listener
        $handle = $entities->onDetach(\Exception::class, function(int $entity, \Exception $component) use (&$detachedEntities, &$detachedComponents) {
            $detachedEntities[] = $entity;
            $detachedComponents[] = $component->getMessage();
        });

        $this->assertIsInt($handle);

        // create entities and attach components
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('e1'));

        $e2 = $entities->create();
        $entities->attach($e2, new \Exception('e2'));

        // detach components
        $entities->detach($e1, \Exception::class);
        $entities->detach($e2, \Exception::class);

        // verify the listener was called
        $this->assertEquals([$e1, $e2], $detachedEntities);
        $this->assertEquals(['e1', 'e2'], $detachedComponents);
    }

    public function testOnDetachListenerWithDetachAll() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);
        $entities->registerComponent(\Error::class);

        $detachedExceptions = [];
        $detachedErrors = [];

        // register detach listeners for both component types
        $entities->onDetach(\Exception::class, function(int $entity, \Exception $component) use (&$detachedExceptions) {
            $detachedExceptions[] = $component->getMessage();
        });

        $entities->onDetach(\Error::class, function(int $entity, \Error $component) use (&$detachedErrors) {
            $detachedErrors[] = $component->getMessage();
        });

        // create entity and attach both components
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('exception'));
        $entities->attach($e1, new \Error('error'));

        // detach all components
        $entities->detachAll($e1);

        // verify both listeners were called
        $this->assertEquals(['exception'], $detachedExceptions);
        $this->assertEquals(['error'], $detachedErrors);
    }

    public function testReleaseOnAttach() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);

        $attachedComponents = [];

        // register an attach listener
        $handle = $entities->onAttach(\Exception::class, function(int $entity, \Exception $component) use (&$attachedComponents) {
            $attachedComponents[] = $component->getMessage();
        });

        // attach a component - listener should be called
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('before release'));

        $this->assertEquals(['before release'], $attachedComponents);

        // release the listener
        $entities->releaseOnAttach($handle);

        // attach another component - listener should NOT be called
        $e2 = $entities->create();
        $entities->attach($e2, new \Exception('after release'));

        // should still be just the first one
        $this->assertEquals(['before release'], $attachedComponents);
    }

    public function testReleaseOnDetach() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);

        $detachedComponents = [];

        // register a detach listener
        $handle = $entities->onDetach(\Exception::class, function(int $entity, \Exception $component) use (&$detachedComponents) {
            $detachedComponents[] = $component->getMessage();
        });

        // create and attach components
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('before release'));

        $e2 = $entities->create();
        $entities->attach($e2, new \Exception('after release'));

        // detach first component - listener should be called
        $entities->detach($e1, \Exception::class);
        $this->assertEquals(['before release'], $detachedComponents);

        // release the listener
        $entities->releaseOnDetach($handle);

        // detach second component - listener should NOT be called
        $entities->detach($e2, \Exception::class);

        // should still be just the first one
        $this->assertEquals(['before release'], $detachedComponents);
    }

    public function testMultipleAttachListeners() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);

        $listener1Called = false;
        $listener2Called = false;

        // register two different attach listeners
        $entities->onAttach(\Exception::class, function(int $entity, \Exception $component) use (&$listener1Called) {
            $listener1Called = true;
        });

        $entities->onAttach(\Exception::class, function(int $entity, \Exception $component) use (&$listener2Called) {
            $listener2Called = true;
        });

        // attach a component
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('test'));

        // both listeners should have been called
        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }

    public function testMultipleDetachListeners() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);

        $listener1Called = false;
        $listener2Called = false;

        // register two different detach listeners
        $entities->onDetach(\Exception::class, function(int $entity, \Exception $component) use (&$listener1Called) {
            $listener1Called = true;
        });

        $entities->onDetach(\Exception::class, function(int $entity, \Exception $component) use (&$listener2Called) {
            $listener2Called = true;
        });

        // create entity and attach component
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('test'));

        // detach the component
        $entities->detach($e1, \Exception::class);

        // both listeners should have been called
        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }

    public function testListenerWithEntityDestroy() : void
    {
        $entities = new EntityRegistry();
        $entities->registerComponent(\Exception::class);

        $detachedComponents = [];

        // register a detach listener
        $entities->onDetach(\Exception::class, function(int $entity, \Exception $component) use (&$detachedComponents) {
            $detachedComponents[] = $component->getMessage();
        });

        // create entity and attach component
        $e1 = $entities->create();
        $entities->attach($e1, new \Exception('to be destroyed'));

        // destroy the entity (which calls detachAll internally)
        $entities->destroy($e1);

        // verify the detach listener was called
        $this->assertEquals(['to be destroyed'], $detachedComponents);
        $this->assertFalse($entities->valid($e1));
    }

    public function testReleaseNonExistentListener() : void
    {
        $entities = new EntityRegistry();

        // releasing non-existent listeners should not cause errors
        $entities->releaseOnAttach(999);
        $entities->releaseOnDetach(999);

        // test should pass without exceptions
        $this->assertTrue(true);
    }
}