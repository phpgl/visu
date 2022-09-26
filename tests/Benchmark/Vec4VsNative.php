<?php

namespace VISU\Tests\Benchmark;

use GL\Math\Vec4;


class Vec4PHPVsNative
{
    /**
     * @Revs(10000)
     */
    public function benchVec4PHPMulGL()
    {
        for ($i = 0; $i < 1000; $i++) {
            $v1 = new Vec4(1.0, 2.0, 3.0, 4.0);
            $v2 = new Vec4(5.0, 6.0, 7.0, 8.0);
            $v3 = $v1 * $v2 * $v1;
            $v4 = $v3->normalize();
        }
    }

    /**
     * @Revs(10000)
     */
    public function benchVec4PHPMulPHP()
    {
        for ($i = 0; $i < 1000; $i++) {
            $v1 = new Vec4PHP(1.0, 2.0, 3.0, 4.0);
            $v2 = new Vec4PHP(5.0, 6.0, 7.0, 8.0);
            $v3 = Vec4PHP::_multiplyVec4($v1, $v2);
            $v3 = Vec4PHP::_multiplyVec4($v3, $v1);
            $v4 = Vec4PHP::_normalize($v3);
        }
    }
}


class Vec4PHP
{
    public float $x;
    public float $y;
    public float $z;
    public float $w;

    public function __construct(float $x, float $y, float $z, float $w) 
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->w = $w;
    }

    public function length() : float
    {
        return sqrt($this->x * $this->x + $this->y * $this->y + $this->z * $this->z + $this->w * $this->w);
    }

    public static function _normalize(Vec4PHP $vector, ?Vec4PHP &$result = null) : Vec4PHP
    {
        if (is_null($result)) $result = new Vec4PHP(0, 0, 0, 0);

        $length = $vector->length();

        if ($length > 0) {
            $length = 1 / $length;
           $result->x = $vector->x * $length;
           $result->y = $vector->y * $length;
           $result->z = $vector->z * $length;
           $result->w = $vector->w * $length;

        } else { 
           $result->x = 0;
           $result->y = 0;
           $result->z = 0;
           $result->w = 0;

        }

        return $result;
    }

    public function normalize()
    {
        Vec4PHP::_normalize($this, $this); return $this;
    }

    public static function _multiplyVec4(Vec4PHP $left, Vec4PHP $right, ?Vec4PHP &$result = null)
    {
        if (is_null($result)) $result = new Vec4PHP(0, 0, 0, 0);
        
        $result->x = $left->x * $right->x;
        $result->y = $left->y * $right->y;
        $result->z = $left->z * $right->z;
        $result->w = $left->w * $right->w;

        return $result;
    }

    public function multiplyVec4(Vec4PHP $right)
    {
        Vec4PHP::_multiplyVec4($this, $right, $this); return $this;
    }
}