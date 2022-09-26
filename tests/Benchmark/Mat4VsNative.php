<?php

namespace VISU\Tests\Benchmark;

use GL\Math\Mat4;


class Mat4PHPVsNative
{
    /**
     * @Revs(10000)
     */
    public function benchMat4PHPMulGL()
    {
        for ($i = 0; $i < 1000; $i++) {
            $m1 = new Mat4;
            $m2 = new Mat4;
            $m3 = $m1 * $m2;
        }
    }

    /**
     * @Revs(10000)
     */
    public function benchMat4PHPMulPHP()
    {
        for ($i = 0; $i < 1000; $i++) {
            $m1 = new Mat4PHP();
            $m2 = new Mat4PHP();
            $m3 = Mat4PHP::_multiply($m1, $m2);
        }
    }
    /**
     * @Revs(10000)
     */
    public function benchMat4PHPInvGL()
    {
        for ($i = 0; $i < 1000; $i++) {
            $m1 = new Mat4;
            $m2 = new Mat4;
            $m3 = $m1 * $m2;
            $m4 = $m3->inverse();
        }
    }

    /**
     * @Revs(10000)
     */
    public function benchMat4PHPInvPHP()
    {
        for ($i = 0; $i < 1000; $i++) {
            $m1 = new Mat4PHP();
            $m2 = new Mat4PHP();
            $m3 = Mat4PHP::_multiply($m1, $m2);
            $m4 = $m3->inverse();
        }
    }
}

class Mat4PHP 
{
    /**
     * Matrix values
     *
     *      [ 0], [ 1], [ 2], [ 3],
     *      [ 4], [ 5], [ 6], [ 7],
     *      [ 8], [ 9], [10], [11],
     *      [12], [13], [14], [15],
     * 
     * @var array
     */
    private array $values = [];

    /**
     * Construct a new matrix
     *
     * @param array                 $values
     */
    public function __construct(?array $values = null)
    {
        if (is_null($values)) {
            $this->reset();
        } else {
            if (count($values) !== 16) throw new \Exception("Invalid data size given to matrix constructor.");
            $this->values = $values;
        }
    }

    /**
     * Reset the matrix to default identity
     *
     * @return void
     */
    public function reset()
    {
        $this->values = [
            1.0, 0.0, 0.0, 0.0,
            0.0, 1.0, 0.0, 0.0,
            0.0, 0.0, 1.0, 0.0,
            0.0, 0.0, 0.0, 1.0,
        ];
    }

    /**
     * Create an orthographic projection matrix
     *
     * @param flaot              $left 
     * @param flaot              $right 
     * @param flaot              $bottom 
     * @param flaot              $top 
     * @param flaot              $near 
     * @param flaot              $far 
     * @param Mat4PHP|null          $result
     *
     * @return Mat4PHP
     */
    public static function ortho(float $left, float $right, float $bottom, float $top, float $near, float $far, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;
        $resultValues = &$result->valueRef();

        $resultValues[0] = -2 / ($left - $right);
        $resultValues[1] = 0.0;
        $resultValues[2] = 0.0;
        $resultValues[3] = 0.0;
        $resultValues[4] = 0.0;
        $resultValues[5] = -2 / ($bottom - $top);
        $resultValues[6] = 0.0;
        $resultValues[7] = 0.0;
        $resultValues[8] = 0.0;
        $resultValues[9] = 0.0;
        $resultValues[10] = 2 / ($near - $far);
        $resultValues[11] = 0.0;
        $resultValues[12] = -($right + $left) / ($right - $left);
        //$resultValues[12] = ($left + $right) * ($left - $right);
        $resultValues[13] = -($top + $bottom) / ($top - $bottom);
        // $resultValues[13] = ($top + $bottom) * ($bottom - $top);
        $resultValues[14] = -($far + $near) / ($far - $near);
        // $resultValues[14] = ($far + $near) * ($near - $far);
        $resultValues[15] = 1.0;

        return $result;
    }

    /**
     * Create an perspective projection matrix
     *
     * @param flaot              $fov 
     * @param flaot              $ratio 
     * @param flaot              $near 
     * @param flaot              $far 
     * @param Mat4PHP|null          $result
     *
     * @return Mat4PHP
     */
    public static function perspective(float $fov, float $ratio, float $near, float $far, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;
        $resultValues = &$result->valueRef();

        $tangent = 1.0 / tan($fov / 2.0);
        $resultValues[0] = $tangent / $ratio;
        $resultValues[1] = 0.0;
        $resultValues[2] = 0.0;
        $resultValues[3] = 0.0;
        $resultValues[4] = 0.0;
        $resultValues[5] = $tangent;
        $resultValues[6] = 0.0;
        $resultValues[7] = 0.0;
        $resultValues[8] = 0.0;
        $resultValues[9] = 0.0;
        $resultValues[10] = ($far + $near) * (1.0 / ($near - $far));
        $resultValues[11] = -1.0;
        $resultValues[12] = 0.0;
        $resultValues[13] = 0.0;
        $resultValues[14] = 2.0 * $far * $near * (1.0 / ($near - $far));
        $resultValues[15] = 0.0;

        return $result;
    }

    /**
     * Multiplication with scalar 
     *
     * @param Mat4PHP              $left 
     * @param float             $value
     * @param Mat4PHP|null         $result
     *
     * @return Mat4PHP
     */
    public static function _multiplyScalar(Mat4PHP $left, float $value, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;
        $resultValues = &$result->valueRef();

        for ($i = 0; $i < 16; ++$i) {
            $resultValues[$i] *= $value;
        }

        return $result;
    }

    /**
     * Multiply by scalar
     *
     * @param float             $value
     */
    public function multiplyScalar(float $value)
    {
        Mat4PHP::_multiplyScalar($this, $value, $this); return $this; 
    }

    /**
     * Multiplication with vector 
     *
     * @param Mat4PHP              $left 
     * @param Vec4              $vec
     * @param Vec4|null         $result
     *
     * @return Vec4
     */
    public static function _multiplyVec4(Mat4PHP $left, Vec4 $vec, ?Vec4 &$result = null) : Vec4
    {
        if (is_null($result)) $result = new Vec4(0.0, 0.0, 0.0, 0.0);
        $leftValues = &$left->valueRef();

        $result->x = $leftValues[0] * $vec->x + $leftValues[4] * $vec->y + $leftValues[8] * $vec->z + $leftValues[12] * $vec->w;
        $result->y = $leftValues[1] * $vec->x + $leftValues[5] * $vec->y + $leftValues[9] * $vec->z + $leftValues[13] * $vec->w;
        $result->z = $leftValues[2] * $vec->x + $leftValues[6] * $vec->y + $leftValues[10] * $vec->z + $leftValues[14] * $vec->w;
        $result->w = $leftValues[3] * $vec->x + $leftValues[7] * $vec->y + $leftValues[11] * $vec->z + $leftValues[15] * $vec->w;

        return $result;
    }

    /**
     * Multiply the current matrix with the given vector
     *
     * @param Vec4                  $vec 
     * @return Vec4
     */ 
    public function multiplyVec4(Vec4 $vec) : Vec4
    {
        return Mat4PHP::_multiplyVec4($this, $vec);
    }

    /**
     * Multiply the current matrix with the given vector
     *
     * @param Vec3                  $vec 
     * @return Vec4
     */ 
    public function multiplyVec3(Vec3 $vec) : Vec4
    {
        return Mat4PHP::_multiplyVec4($this, new Vec4($vec->x, $vec->y, $vec->z, 1.0));
    }

    /**
     * Multiplication with vector 
     *
     * @param Mat4PHP              $left 
     * @param Mat4PHP              $right
     * @param Mat4PHP|null         $result
     *
     * @return Mat4PHP
     */
    public static function _multiply(Mat4PHP $left, Mat4PHP $right, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;

        // dont multiply already multiplied values
        if ($left === $result) {
            $leftValues = $left->raw();
        } else {
            $leftValues = &$left->valueRef();
        }

        $rightValues = &$right->valueRef();
        $resultValues = &$result->valueRef();

        $resultValues[0] = (($leftValues[0] * $rightValues[0]) +
             ($leftValues[1] * $rightValues[4]) +
             ($leftValues[2] * $rightValues[8]) +
             ($leftValues[3] * $rightValues[12]));
        $resultValues[1] = (($leftValues[0] * $rightValues[1]) +
             ($leftValues[1] * $rightValues[5]) +
             ($leftValues[2] * $rightValues[9]) +
             ($leftValues[3] * $rightValues[13]));
        $resultValues[2] = (($leftValues[0] * $rightValues[2]) +
             ($leftValues[1] * $rightValues[6]) +
             ($leftValues[2] * $rightValues[10]) +
             ($leftValues[3] * $rightValues[14]));
        $resultValues[3] = (($leftValues[0] * $rightValues[3]) +
             ($leftValues[1] * $rightValues[7]) +
             ($leftValues[2] * $rightValues[11]) +
             ($leftValues[3] * $rightValues[15]));

        $resultValues[4] = (($leftValues[4] * $rightValues[0]) +
             ($leftValues[5] * $rightValues[4]) +
             ($leftValues[6] * $rightValues[8]) +
             ($leftValues[7] * $rightValues[12]));
        $resultValues[5] = (($leftValues[4] * $rightValues[1]) +
             ($leftValues[5] * $rightValues[5]) +
             ($leftValues[6] * $rightValues[9]) +
             ($leftValues[7] * $rightValues[13]));
        $resultValues[6] = (($leftValues[4] * $rightValues[2]) +
             ($leftValues[5] * $rightValues[6]) +
             ($leftValues[6] * $rightValues[10]) +
             ($leftValues[7] * $rightValues[14]));
        $resultValues[7] = (($leftValues[4] * $rightValues[3]) +
             ($leftValues[5] * $rightValues[7]) +
             ($leftValues[6] * $rightValues[11]) +
             ($leftValues[7] * $rightValues[15]));

        $resultValues[8] = (($leftValues[8] * $rightValues[0]) +
             ($leftValues[9] * $rightValues[4]) +
             ($leftValues[10] * $rightValues[8]) +
             ($leftValues[11] * $rightValues[12]));
        $resultValues[9] = (($leftValues[8] * $rightValues[1]) +
             ($leftValues[9] * $rightValues[5]) +
             ($leftValues[10] * $rightValues[9]) +
             ($leftValues[11] * $rightValues[13]));
        $resultValues[10] = (($leftValues[8] * $rightValues[2]) +
             ($leftValues[9] * $rightValues[6]) +
             ($leftValues[10] * $rightValues[10]) +
             ($leftValues[11] * $rightValues[14]));
        $resultValues[11] = (($leftValues[8] * $rightValues[3]) +
             ($leftValues[9] * $rightValues[7]) +
             ($leftValues[10] * $rightValues[11]) +
             ($leftValues[11] * $rightValues[15]));

        $resultValues[12] = (($leftValues[12] * $rightValues[0]) +
             ($leftValues[13] * $rightValues[4]) +
             ($leftValues[14] * $rightValues[8]) +
             ($leftValues[15] * $rightValues[12]));
        $resultValues[13] = (($leftValues[12] * $rightValues[1]) +
             ($leftValues[13] * $rightValues[5]) +
             ($leftValues[14] * $rightValues[9]) +
             ($leftValues[15] * $rightValues[13]));
        $resultValues[14] = (($leftValues[12] * $rightValues[2]) +
             ($leftValues[13] * $rightValues[6]) +
             ($leftValues[14] * $rightValues[10]) +
             ($leftValues[15] * $rightValues[14]));
        $resultValues[15] = (($leftValues[12] * $rightValues[3]) +
             ($leftValues[13] * $rightValues[7]) +
             ($leftValues[14] * $rightValues[11]) +
             ($leftValues[15] * $rightValues[15]));  

        return $result;
    }

    /**
     * Multiply the current matrix with the given vector
     *
     * @param Mat4PHP                  $vec 
     * @param bool                  $createNew
     * @return Mat4PHP
     */ 
    public function multiply(Mat4PHP $right, bool $createNew = false) : Mat4PHP
    {
        $result = null;
        if ($createNew === false) $result = $this; 
        return Mat4PHP::_multiply($this, $right, $result);
    }

    /**
     * Translate the matrix
     *
     * @param Mat4PHP              $left 
     * @param Vec3              $vec
     * @param Mat4PHP|null         $result
     *
     * @return Mat4PHP
     */
    public static function _translate(Mat4PHP $left, Vec3 $vec, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;

        // dont multiply already multiplied values
        if ($left === $result) {
            $leftValues = $left->raw();
        } else {
            $leftValues = &$left->valueRef();
        }

        $resultValues = &$result->valueRef();

        $resultValues[12] = $leftValues[0] * $vec->x + $leftValues[4] * $vec->y + $leftValues[8] * $vec->z + $leftValues[12];
        $resultValues[13] = $leftValues[1] * $vec->x + $leftValues[5] * $vec->y + $leftValues[9] * $vec->z + $leftValues[13];
        $resultValues[14] = $leftValues[2] * $vec->x + $leftValues[6] * $vec->y + $leftValues[10] * $vec->z + $leftValues[14];
        $resultValues[15] = $leftValues[3] * $vec->x + $leftValues[7] * $vec->y + $leftValues[11] * $vec->z + $leftValues[15];  

        return $result;
    }

    /**
     * Translate the current matrix with the given vector
     *
     * @param Vec3                  $vec 
     * @return Mat4PHP
     */ 
    public function translate(Vec3 $vec) : Mat4PHP
    {
        return Mat4PHP::_translate($this, $vec, $this);
    }

    /**
     * Rotate the matrix by x
     *
     * @param Mat4PHP              $left 
     * @param float             $radians
     * @param Mat4PHP|null         $result
     *
     * @return Mat4PHP
     */
    public static function _rotateX(Mat4PHP $left, float $radians, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;

        // dont multiply already multiplied values
        if ($left === $result) {
            $leftValues = $left->raw();
        } else {
            $leftValues = &$left->valueRef();
        }

        $resultValues = &$result->valueRef();

        $rsin = sin($radians);
        $rcos = cos($radians);

        $resultValues[4] = $leftValues[4] * $rcos + $leftValues[8] * $rsin;
        $resultValues[5] = $leftValues[5] * $rcos + $leftValues[9] * $rsin;
        $resultValues[6] = $leftValues[6] * $rcos + $leftValues[10] * $rsin;
        $resultValues[7] = $leftValues[7] * $rcos + $leftValues[11] * $rsin;
        $resultValues[8] = $leftValues[8] * $rcos - $leftValues[4] * $rsin;
        $resultValues[9] = $leftValues[9] * $rcos - $leftValues[5] * $rsin;
        $resultValues[10] = $leftValues[10] * $rcos - $leftValues[6] * $rsin;
        $resultValues[11] = $leftValues[11] * $rcos - $leftValues[7] * $rsin;

        return $result;
    }

    /**
     * Rotate the current matrix on the x axis
     *
     * @param float                  $radians 
     * @return Mat4PHP
     */ 
    public function rotateX(float $radians) : Mat4PHP
    {
        return Mat4PHP::_rotateX($this, $radians, $this);
    }

    /**
     * Rotate the matrix by y
     *
     * @param Mat4PHP              $left 
     * @param float             $radians
     * @param Mat4PHP|null         $result
     *
     * @return Mat4PHP
     */
    public static function _rotateY(Mat4PHP $left, float $radians, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;

        // dont multiply already multiplied values
        if ($left === $result) {
            $leftValues = $left->raw();
        } else {
            $leftValues = &$left->valueRef();
        }

        $resultValues = &$result->valueRef();

        $rsin = sin($radians);
        $rcos = cos($radians);

        $resultValues[0] = $leftValues[0] * $rcos - $leftValues[8] * $rsin;
        $resultValues[1] = $leftValues[1] * $rcos - $leftValues[9] * $rsin;
        $resultValues[2] = $leftValues[2] * $rcos - $leftValues[10] * $rsin;
        $resultValues[3] = $leftValues[3] * $rcos - $leftValues[11] * $rsin;
        $resultValues[8] = $leftValues[0] * $rsin + $leftValues[8] * $rcos;
        $resultValues[9] = $leftValues[1] * $rsin + $leftValues[9] * $rcos;
        $resultValues[10] = $leftValues[2] * $rsin + $leftValues[10] * $rcos;
        $resultValues[11] = $leftValues[3] * $rsin + $leftValues[11] * $rcos;

        return $result;
    }

    /**
     * Rotate the current matrix on the y axis
     *
     * @param float                  $radians 
     * @return Mat4PHP
     */ 
    public function rotateY(float $radians) : Mat4PHP
    {
        return Mat4PHP::_rotateY($this, $radians, $this);
    }

    /**
     * Invert a matrix
     *
     * @param Mat4PHP              $left 
     * @param float             $radians
     * @param Mat4PHP|null         $result
     *
     * @return Mat4PHP
     */
    public static function _inverse(Mat4PHP $left, ?Mat4PHP &$result = null) : Mat4PHP
    {
        if (is_null($result)) $result = new Mat4PHP;

        // dont update already updated values
        if ($left === $result) {
            $leftValues = $left->raw();
        } else {
            $leftValues = &$left->valueRef();
        }

        $resultValues = &$result->valueRef();


        $cof1 = $leftValues[0] * $leftValues[5] - $leftValues[1] * $leftValues[4];
        $cof2 = $leftValues[0] * $leftValues[6] - $leftValues[2] * $leftValues[4];
        $cof3 = $leftValues[0] * $leftValues[7] - $leftValues[3] * $leftValues[4];
        $cof4 = $leftValues[1] * $leftValues[6] - $leftValues[2] * $leftValues[5];
        $cof5 = $leftValues[1] * $leftValues[7] - $leftValues[3] * $leftValues[5];
        $cof6 = $leftValues[2] * $leftValues[7] - $leftValues[3] * $leftValues[6];
        $cof7 = $leftValues[8] * $leftValues[13] - $leftValues[9] * $leftValues[12];
        $cof8 = $leftValues[8] * $leftValues[14] - $leftValues[10] * $leftValues[12];
        $cof9 = $leftValues[8] * $leftValues[15] - $leftValues[11] * $leftValues[12];
        $cof10 = $leftValues[9] * $leftValues[14] - $leftValues[10] * $leftValues[13];
        $cof11 = $leftValues[9] * $leftValues[15] - $leftValues[11] * $leftValues[13];
        $cof12 = $leftValues[10] * $leftValues[15] - $leftValues[11] * $leftValues[14];

        $determinant = 
          $cof1 * $cof12 - $cof2 * $cof11 + 
          $cof3 * $cof10 + $cof4 * $cof9 - 
          $cof5 * $cof8 + $cof6 * $cof7;
          
        if ($determinant == 0) {
            throw new \Exception("Division by 0");
        }

        $determinant = 1.0 / $determinant;

        $resultValues[0] = ($leftValues[5] * $cof12 - $leftValues[6] * $cof11 + $leftValues[7] * $cof10) * $determinant;
        $resultValues[1] = ($leftValues[2] * $cof11 - $leftValues[1] * $cof12 - $leftValues[3] * $cof10) * $determinant;
        $resultValues[2] = ($leftValues[13] * $cof6 - $leftValues[14] * $cof5 + $leftValues[15] * $cof4) * $determinant;
        $resultValues[3] = ($leftValues[10] * $cof5 - $leftValues[9] * $cof6 - $leftValues[11] * $cof4) * $determinant;
        $resultValues[4] = ($leftValues[6] * $cof9 - $leftValues[4] * $cof12 - $leftValues[7] * $cof8) * $determinant;
        $resultValues[5] = ($leftValues[0] * $cof12 - $leftValues[2] * $cof9 + $leftValues[3] * $cof8) * $determinant;
        $resultValues[6] = ($leftValues[14] * $cof3 - $leftValues[12] * $cof6 - $leftValues[15] * $cof2) * $determinant;
        $resultValues[7] = ($leftValues[8] * $cof6 - $leftValues[10] * $cof3 + $leftValues[11] * $cof2) * $determinant;
        $resultValues[8] = ($leftValues[4] * $cof11 - $leftValues[5] * $cof9 + $leftValues[7] * $cof7) * $determinant;
        $resultValues[9] = ($leftValues[1] * $cof9 - $leftValues[0] * $cof11 - $leftValues[3] * $cof7) * $determinant;
        $resultValues[10] = ($leftValues[12] * $cof5 - $leftValues[13] * $cof3 + $leftValues[15] * $cof1) * $determinant;
        $resultValues[11] = ($leftValues[9] * $cof3 - $leftValues[8] * $cof5 - $leftValues[11] * $cof1) * $determinant;
        $resultValues[12] = ($leftValues[5] * $cof8 - $leftValues[4] * $cof10 - $leftValues[6] * $cof7) * $determinant;
        $resultValues[13] = ($leftValues[0] * $cof10 - $leftValues[1] * $cof8 + $leftValues[2] * $cof7) * $determinant;
        $resultValues[14] = ($leftValues[13] * $cof2 - $leftValues[12] * $cof4 - $leftValues[14] * $cof1) * $determinant;
        $resultValues[15] = ($leftValues[8] * $cof4 - $leftValues[9] * $cof2 + $leftValues[10] * $cof1) * $determinant;
                

        return $result;
    }

    /**
     * Inverse the current matrix
     *
     * @param Vec3                  $vec 
     * @return Mat4PHP
     */ 
    public function inverse() : Mat4PHP
    {
        return Mat4PHP::_inverse($this, $this);
    }

    /**
     * Get the matrix data
     *
     * @return array
     */
    public function raw() : array
    {
        return $this->values;
    }

    public function &valueRef() : array
    {
        return $this->values;
    }

    /**
     * Dump the values  
     */
    public function __toString()
    {
        return sprintf(
'Mat4PHP:
(
    [%.2f, %.2f, %.2f, %.2f]
    [%.2f, %.2f, %.2f, %.2f]
    [%.2f, %.2f, %.2f, %.2f]
    [%.2f, %.2f, %.2f, %.2f]
)', ...$this->values
        );
    }
}