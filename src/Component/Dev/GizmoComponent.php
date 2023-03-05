<?php

namespace VISU\Component\Dev;

use GL\Math\Vec3;
use VISU\Geo\AABB;

class GizmoComponent
{
    /**
     * AABB of the X translation gizmo
     */
    public AABB $aabbTranslateX ;

    /**
     * AABB of the Y translation gizmo
     */
    public AABB $aabbTranslateY;

    /**
     * AABB of the Z translation gizmo
     */
    public AABB $aabbTranslateZ;

    /**
     * The scale of the Gizmo
     */
    public float $scale = 1.0;

    /**
     * The current snapping grid
     */
    public float $snapGrid = 0.0; // 0.0 = no snapping

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->aabbTranslateX = new AABB(new Vec3, new Vec3);
        $this->aabbTranslateY = new AABB(new Vec3, new Vec3);
        $this->aabbTranslateZ = new AABB(new Vec3, new Vec3);
    }
}