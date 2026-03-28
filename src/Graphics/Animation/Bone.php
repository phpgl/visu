<?php

namespace VISU\Graphics\Animation;

use GL\Math\Mat4;

class Bone
{
    /**
     * Inverse bind matrix (transforms from mesh space to bone-local space in bind pose)
     */
    public Mat4 $inverseBindMatrix;

    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly int $parentIndex,
    ) {
        $this->inverseBindMatrix = new Mat4();
    }
}
