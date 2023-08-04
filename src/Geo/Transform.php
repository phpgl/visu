<?php

namespace VISU\Geo;

use GL\Math\Mat4;
use GL\Math\Vec3;
use GL\Math\Quat;
use VISU\ECS\EntitiesInterface;

class Transform
{
    /**
     * Returns a new Vec3 with a vector pointing up in the world.
     */
    public static function worldUp() : Vec3
    {
        return new Vec3(0.0, 1.0, 0.0);
    }

    /**
     * Returns a new Vec3 with a vector pointing down in the world.
     */
    public static function worldDown() : Vec3
    {
        return new Vec3(0.0, -1.0, 0.0);
    }

    /**
     * Returns a new Vec3 with a vector pointing right in the world.
     */
    public static function worldRight() : Vec3
    {
        return new Vec3(1.0, 0.0, 0.0);
    }

    /**
     * Returns a new Vec3 with a vector pointing left in the world.
     */
    public static function worldLeft() : Vec3
    {
        return new Vec3(-1.0, 0.0, 0.0);
    }

    /**
     * Returns a new Vec3 with a vector pointing forward in the world.
     */
    public static function worldForward() : Vec3
    {
        return new Vec3(0.0, 0.0, -1.0);
    }

    /**
     * Returns a new Vec3 with a vector pointing backward in the world.
     */
    public static function worldBackward() : Vec3
    {
        return new Vec3(0.0, 0.0, 1.0);
    }

    /**
     * Internal matrix representation.
     */
    private Mat4 $matrix;

    /**
     * Flag indicating whether the matrix is dirty and needs to be recalculated.
     */
    public bool $isDirty = true;

    /**
     * Current position in local space.
     */
    public Vec3 $position;

    /**
     * Current orientation in local space.
     */
    public Quat $orientation;

    /**
     * Current scale in local space.
     */
    public Vec3 $scale;

    /**
     * Parent entity id.
     */
    public ?int $parent = null;

    /**
     * Prvious transform
     * Can story a copy of the current transform before it was modified.
     * This can be used for interpolation of the transformation between frames.
     */
    private ?Transform $previous = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->matrix = new Mat4();
        $this->position = new Vec3();
        $this->orientation = new Quat();
        $this->scale = new Vec3(1.0, 1.0, 1.0);
    }

    /**
     * Sets the parent entity id.
     * 
     * @param EntitiesInterface $entities The entity registry the parent entity is stored in.
     * @param int $parent 
     */
    public function setParent(EntitiesInterface $entities, int $parent) : void
    {
        if (!$entities->has($parent, Transform::class)) {
            throw new \InvalidArgumentException("Entity $parent must have a Transform component attached to it.");
        }

        $this->parent = $parent;
    }

    /**
     * Returns the parent Transform component.
     * 
     * @param EntitiesInterface $entities The entity registry the parent entity is stored in.
     * @return Transform|null 
     */
    public function getParent(EntitiesInterface $entities) : ?Transform
    {
        if ($this->parent === null) {
            return null;
        }

        return $entities->get($this->parent, Transform::class);
    }

    /**
     * Returns the local matrix.
     * 
     * @return Mat4 
     */
    public function getLocalMatrix() : Mat4
    {
        if ($this->isDirty) {
            $this->matrix = new Mat4();
            $this->matrix->translate($this->position);
            // $this->matrix * $this->orientation
            $this->matrix = Mat4::multiplyQuat($this->matrix, $this->orientation);
            $this->matrix->scale($this->scale);
            $this->isDirty = false;
        }

        return $this->matrix;
    }

    /**
     * Returns the world matrix.
     * 
     * @param EntitiesInterface $entities The entity registry the parent entity is stored in.
     * @return Mat4 
     */
    public function getWorldMatrix(EntitiesInterface $entities) : Mat4
    {
        $matrix = $this->getLocalMatrix();
        $parent = $this->getParent($entities);

        if ($parent !== null) {
            $parentWorld = $parent->getWorldMatrix($entities);
            return $parentWorld * $matrix;
        }

        return $matrix;
    }

    /**
     * Returns the position in world space.
     * 
     * @param EntitiesInterface $entities The entity registry the parent entity is stored in.
     * @return Vec3 
     */
    public function getWorldPosition(EntitiesInterface $entities) : Vec3
    {
        if ($parent = $this->getParent($entities)) {
            return $parent->getWorldPosition($entities) + $this->position;
        }

        return $this->position;
    }

    /**
     * Returns the orientation in world space.
     * 
     * @param EntitiesInterface $entities The entity registry the parent entity is stored in.
     * @return Quat 
     */
    public function getWorldOrientation(EntitiesInterface $entities) : Quat
    {
        if ($parent = $this->getParent($entities)) {
            return $parent->getWorldOrientation($entities) * $this->orientation;
        }

        return $this->orientation;
    }

    /**
     * Returns the scale in world space.
     * 
     * @param EntitiesInterface $entities The entity registry the parent entity is stored in.
     * @return Vec3 
     */
    public function getWorldScale(EntitiesInterface $entities) : Vec3
    {
        if ($parent = $this->getParent($entities)) {
            return $parent->getWorldScale($entities) * $this->scale;
        }

        return $this->scale;
    }

    /**
     * Retruns the orientation as euler angles.
     * 
     * @return Vec3 
     */
    public function getLocalEulerAngles() : Vec3
    {
        return $this->orientation->eulerAngles();
    }

    /**
     * Returns the orientation as euler angles in world space.
     * 
     * @param EntitiesInterface $entities The entity registry the parent entity is stored in.
     * @return Vec3 
     */
    public function getWorldEulerAngles(EntitiesInterface $entities) : Vec3
    {
        return $this->getWorldOrientation($entities)->eulerAngles();
    }

    /**
     * Flags the internal matrix as dirty.
     * When you modify the position, orientation or scale of the transform manually
     * You have to call this method to make sure the internal matrix is recalculated.
     */
    public function markDirty() : void
    {
        $this->isDirty = true;
    }

    /**
     * Sets the position of the transform.
     * Note: This will mark the internal matrix as dirty.
     */
    public function setPosition(Vec3 $position) : void
    {
        $this->position = $position;
        $this->isDirty = true;
    }

    /**
     * Sets the orientation of the transform.
     * Note: This will mark the internal matrix as dirty.
     */
    public function setOrientation(Quat $orientation) : void
    {
        $this->orientation = $orientation;
        $this->isDirty = true;
    }

    /**
     * Sets the scale of the transform.
     * Note: This will mark the internal matrix as dirty.
     */
    public function setScale(Vec3 $scale) : void
    {
        $this->scale = $scale;
        $this->isDirty = true;
    }

    /**
     * Returns the forward direction of the transform
     */
    public function dirForward() : Vec3
    {
        return Quat::multiplyVec3($this->orientation, self::worldForward());
    }

    /**
     * Returns the backward direction of the transform
     */
    public function dirBackward() : Vec3
    {
        return Quat::multiplyVec3($this->orientation, self::worldBackward());
    }

    /**
     * Returns the right direction of the transform
     */
    public function dirRight() : Vec3
    {
        return Quat::multiplyVec3($this->orientation, self::worldRight());
    }

    /**
     * Returns the left direction of the transform
     */
    public function dirLeft() : Vec3
    {
        return Quat::multiplyVec3($this->orientation, self::worldLeft());
    }

    /**
     * Returns the up direction of the transform
     */
    public function dirUp() : Vec3
    {
        return Quat::multiplyVec3($this->orientation, self::worldUp());
    }

    /**
     * Returns the down direction of the transform
     */
    public function dirDown() : Vec3
    {
        return Quat::multiplyVec3($this->orientation, self::worldDown());
    }

    /**
     * Translates forward in local space.
     */
    public function moveForward(float $distance) : void
    {   
        // $dir = $this->orientation * self::worldForward();
        $dir = Quat::multiplyVec3($this->orientation, self::worldForward());
        $this->position += $dir * $distance;
        $this->isDirty = true;
    }

    /**
     * Translates backward in local space.
     */
    public function moveBackward(float $distance) : void
    {
        // $dir = $this->orientation * self::worldBackward();
        $dir = Quat::multiplyVec3($this->orientation, self::worldBackward());
        $this->position += $dir * $distance;
        $this->isDirty = true;
    }

    /**
     * Translates right in local space.
     */
    public function moveRight(float $distance) : void
    {
        // $dir = $this->orientation * self::worldRight();
        $dir = Quat::multiplyVec3($this->orientation, self::worldRight());
        $this->position += $dir * $distance;
        $this->isDirty = true;
    }

    /**
     * Translates left in local space.
     */
    public function moveLeft(float $distance) : void
    {
        // $dir = $this->orientation * self::worldLeft();
        $dir = Quat::multiplyVec3($this->orientation, self::worldLeft());
        $this->position += $dir * $distance;
        $this->isDirty = true;
    }

    /**
     * Translates up in local space.
     */
    public function moveUp(float $distance) : void
    {
        // $dir = $this->orientation * self::worldUp();
        $dir = Quat::multiplyVec3($this->orientation, self::worldUp());
        $this->position += $dir * $distance;
        $this->isDirty = true;
    }

    /**
     * Translates down in local space.
     */
    public function moveDown(float $distance) : void
    {
        // $dir = $this->orientation * self::worldDown();
        $dir = Quat::multiplyVec3($this->orientation, self::worldDown());
        $this->position += $dir * $distance;
        $this->isDirty = true;
    }

    /**
     * Look at a point in world space.
     */
    public function lookAt(Vec3 $point) : void
    {
        $mat = new Mat4();
        $mat->lookAt($this->position, $point, self::worldUp());
        
        $this->orientation = Quat::fromMat4($mat);

        // because we have no conjugate method yet
        $this->orientation = new Quat(
            -$this->orientation->w,
            $this->orientation->x,
            $this->orientation->y,
            $this->orientation->z,
        );

        $this->isDirty = true;
    }

    /**
     * Look in a direction in world space.
     */
    public function lookIn(Vec3 $direction) : void
    {
        $this->lookAt($this->position + $direction);
    }

    /**
     * Calling this function will store a copy of the current transform as the previous transform.
     */
    public function storePrevious() : void
    {
        $this->previous = new Transform();
        $this->previous->position = $this->position->copy();
        $this->previous->orientation = $this->orientation->copy();
        $this->previous->scale = $this->scale->copy();
    }

    /**
     * Resets the previous transform.
     */
    public function resetPrevious() : void
    {
        $this->previous = null;
    }

    /**
     * Returns the interpolated position between the previous and current transform.
     */
    public function getInterpolatedPosition(float $alpha) : Vec3
    {
        if ($this->previous === null) {
            return $this->position;
        }

        return Vec3::lerp($this->previous->position, $this->position, $alpha);
    }

    /**
     * Returns the interpolated orientation between the previous and current transform.
     */
    public function getInterpolatedOrientation(float $alpha) : Quat
    {
        if ($this->previous === null) {
            return $this->orientation;
        }

        return Quat::slerp($this->previous->orientation, $this->orientation, $alpha);
    }

    /**
     * Returns the interpolated scale between the previous and current transform.
     */
    public function getInterpolatedScale(float $alpha) : Vec3
    {
        if ($this->previous === null) {
            return $this->scale;
        }

        return Vec3::lerp($this->previous->scale, $this->scale, $alpha);
    }

    /**
     * Returns a interpolated matrix between the previous and current transform.
     */
    public function getInterpolatedLocalMatrix(float $alpha) : Mat4
    {
        if ($this->previous === null) {
            return $this->getLocalMatrix();
        }

        $matrix = new Mat4();
        $matrix->translate(Vec3::lerp($this->previous->position, $this->position, $alpha));
        $matrix = Mat4::multiplyQuat($matrix, Quat::slerp($this->previous->orientation, $this->orientation, $alpha));
        $matrix->scale(Vec3::lerp($this->previous->scale, $this->scale, $alpha));

        return $matrix;
    }

    public function __clone() 
    {
        // be sure to make a deep copy of the internal objects
        $this->position = unserialize(serialize($this->position));
        $this->orientation = unserialize(serialize($this->orientation));
        $this->scale = unserialize(serialize($this->scale));
        $this->matrix = unserialize(serialize($this->matrix));
    }

    /**
     * serialize
     *
     * @param Transform $transform
     * @return array<mixed>
     */
    public static function serialize(Transform $transform) : array
    {
        return [
            'position' => Serializer::serializeVec3($transform->position),
            'orientation' => Serializer::serializeQuat($transform->orientation),
            'scale' => Serializer::serializeVec3($transform->scale),
            'parent' => $transform->parent,
        ];
    }

    /**
     * unserialize
     *
     * @param array<mixed> $data
     * @return Transform
     */
    public static function unserialize(array $data) : Transform
    {
        $transform = new Transform();
        $transform->position = Serializer::deserializeVec3($data['position']);
        $transform->orientation = Serializer::deserializeQuat($data['orientation']);
        $transform->scale = Serializer::deserializeVec3($data['scale']);
        $transform->parent = $data['parent'];
        $transform->isDirty = true;
        return $transform;
    }
}
