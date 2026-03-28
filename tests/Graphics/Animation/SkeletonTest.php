<?php

namespace Tests\Graphics\Animation;

use PHPUnit\Framework\TestCase;
use VISU\Graphics\Animation\Bone;
use VISU\Graphics\Animation\Skeleton;

class SkeletonTest extends TestCase
{
    public function testAddAndRetrieveBone(): void
    {
        $skeleton = new Skeleton();
        $bone = new Bone(0, 'root', -1);
        $skeleton->addBone($bone);

        $this->assertEquals(1, $skeleton->boneCount());
        $this->assertSame($bone, $skeleton->getBoneByName('root'));
        $this->assertEquals(0, $skeleton->getBoneIndex('root'));
    }

    public function testBoneHierarchy(): void
    {
        $skeleton = new Skeleton();
        $skeleton->addBone(new Bone(0, 'root', -1));
        $skeleton->addBone(new Bone(1, 'spine', 0));
        $skeleton->addBone(new Bone(2, 'head', 1));

        $this->assertEquals(3, $skeleton->boneCount());
        $this->assertEquals(0, $skeleton->bones[1]->parentIndex);
        $this->assertEquals(1, $skeleton->bones[2]->parentIndex);
    }

    public function testUnknownBoneReturnsNull(): void
    {
        $skeleton = new Skeleton();
        $this->assertNull($skeleton->getBoneByName('nonexistent'));
        $this->assertEquals(-1, $skeleton->getBoneIndex('nonexistent'));
    }
}
