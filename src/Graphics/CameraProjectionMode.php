<?php

namespace VISU\Graphics;

enum CameraProjectionMode {
    case perspective;
    case orthographicWorld;
    case orthographicScreen;
    case orthographicStaticWorld;
}