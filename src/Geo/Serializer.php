<?php

namespace VISU\Geo;

use GL\Math\Mat4;
use GL\Math\Quat;
use GL\Math\Vec2;
use GL\Math\Vec3;
use GL\Math\Vec4;

/**
 * This contains a collection of slow serialization methods.
 * Until we support serialization in the GLM library, this will have to do.
 */
class Serializer
{
    public static function serializeVec2(Vec2 $vec) : string
    {
        return sprintf('%f %f', $vec->x, $vec->y);
    }

    public static function deserializeVec2(string $str) : Vec2
    {
        $parts = explode(' ', $str);
        return new Vec2((float)$parts[0], (float)$parts[1]);
    }

    public static function serializeVec3(Vec3 $vec) : string
    {
        return sprintf('%f %f %f', $vec->x, $vec->y, $vec->z);
    }

    public static function deserializeVec3(string $str) : Vec3
    {
        $parts = explode(' ', $str);
        return new Vec3((float)$parts[0], (float)$parts[1], (float)$parts[2]);
    }

    public static function serializeVec4(Vec4 $vec) : string
    {
        return sprintf('%f %f %f %f', $vec->x, $vec->y, $vec->z, $vec->w);
    }

    public static function deserializeVec4(string $str) : Vec4
    {
        $parts = explode(' ', $str);
        return new Vec4((float)$parts[0], (float)$parts[1], (float)$parts[2], (float)$parts[3]);
    }

    public static function serializeQuat(Quat $quat) : string
    {
        return sprintf('%f %f %f %f', $quat->w, $quat->x, $quat->y, $quat->z);
    }

    public static function deserializeQuat(string $str) : Quat
    {
        $parts = explode(' ', $str);
        return new Quat((float)$parts[0], (float)$parts[1], (float)$parts[2], (float)$parts[3]);
    }

    public static function serializeMat4(Mat4 $mat) : string
    {
        $b = '';
        for ($i = 0; $i < 16; $i++) {
            $b .= $mat[$i] . ' ';
        }
        return $b;
    }

    public static function deserializeMat4(string $str) : Mat4
    {
        $parts = explode(' ', $str);
        $mat = new Mat4();
        for ($i = 0; $i < 16; $i++) {
            $mat[$i] = (float)$parts[$i];
        }
        return $mat;
    }
}
