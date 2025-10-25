<?php 

namespace VISU\OS;

class Key
{
    /**
     * We warp the GLFW keys constants to allow a syntax like this:
     * 
     * ```php
     * $input->getKeyState(Input\Key::SPACE) === INPUT::PRESS
     * ```
     */
    // mods
    public const MOD_SHIFT = GLFW_MOD_SHIFT;
    public const MOD_CONTROL = GLFW_MOD_CONTROL;
    public const MOD_ALT = GLFW_MOD_ALT;
    public const MOD_SUPER = GLFW_MOD_SUPER;
    public const MOD_CAPS_LOCK = GLFW_MOD_CAPS_LOCK;
    public const MOD_NUM_LOCK = GLFW_MOD_NUM_LOCK;

    // keys
    public const UNKNOWN = GLFW_KEY_UNKNOWN;
    public const SPACE = GLFW_KEY_SPACE;
    public const APOSTROPHE = GLFW_KEY_APOSTROPHE;
    public const COMMA = GLFW_KEY_COMMA;
    public const MINUS = GLFW_KEY_MINUS;
    public const PERIOD = GLFW_KEY_PERIOD;
    public const SLASH = GLFW_KEY_SLASH;
    public const NUM_0 = GLFW_KEY_0;
    public const NUM_1 = GLFW_KEY_1;
    public const NUM_2 = GLFW_KEY_2;
    public const NUM_3 = GLFW_KEY_3;
    public const NUM_4 = GLFW_KEY_4;
    public const NUM_5 = GLFW_KEY_5;
    public const NUM_6 = GLFW_KEY_6;
    public const NUM_7 = GLFW_KEY_7;
    public const NUM_8 = GLFW_KEY_8;
    public const NUM_9 = GLFW_KEY_9;
    public const SEMICOLON = GLFW_KEY_SEMICOLON;
    public const EQUAL = GLFW_KEY_EQUAL;
    public const A = GLFW_KEY_A;
    public const B = GLFW_KEY_B;
    public const C = GLFW_KEY_C;
    public const D = GLFW_KEY_D;
    public const E = GLFW_KEY_E;
    public const F = GLFW_KEY_F;
    public const G = GLFW_KEY_G;
    public const H = GLFW_KEY_H;
    public const I = GLFW_KEY_I;
    public const J = GLFW_KEY_J;
    public const K = GLFW_KEY_K;
    public const L = GLFW_KEY_L;
    public const M = GLFW_KEY_M;
    public const N = GLFW_KEY_N;
    public const O = GLFW_KEY_O;
    public const P = GLFW_KEY_P;
    public const Q = GLFW_KEY_Q;
    public const R = GLFW_KEY_R;
    public const S = GLFW_KEY_S;
    public const T = GLFW_KEY_T;
    public const U = GLFW_KEY_U;
    public const V = GLFW_KEY_V;
    public const W = GLFW_KEY_W;
    public const X = GLFW_KEY_X;
    public const Y = GLFW_KEY_Y;
    public const Z = GLFW_KEY_Z;
    public const LEFT_BRACKET = GLFW_KEY_LEFT_BRACKET;
    public const BACKSLASH = GLFW_KEY_BACKSLASH;
    public const RIGHT_BRACKET = GLFW_KEY_RIGHT_BRACKET;
    public const GRAVE_ACCENT = GLFW_KEY_GRAVE_ACCENT;
    public const WORLD_1 = GLFW_KEY_WORLD_1;
    public const WORLD_2 = GLFW_KEY_WORLD_2;
    public const ESCAPE = GLFW_KEY_ESCAPE;
    public const ENTER = GLFW_KEY_ENTER;
    public const TAB = GLFW_KEY_TAB;
    public const BACKSPACE = GLFW_KEY_BACKSPACE;
    public const INSERT = GLFW_KEY_INSERT;
    public const DELETE = GLFW_KEY_DELETE;
    public const RIGHT = GLFW_KEY_RIGHT;
    public const LEFT = GLFW_KEY_LEFT;
    public const DOWN = GLFW_KEY_DOWN;
    public const UP = GLFW_KEY_UP;
    public const PAGE_UP = GLFW_KEY_PAGE_UP;
    public const PAGE_DOWN = GLFW_KEY_PAGE_DOWN;
    public const HOME = GLFW_KEY_HOME;
    public const END = GLFW_KEY_END;
    public const CAPS_LOCK = GLFW_KEY_CAPS_LOCK;
    public const SCROLL_LOCK = GLFW_KEY_SCROLL_LOCK;
    public const NUM_LOCK = GLFW_KEY_NUM_LOCK;
    public const PRINT_SCREEN = GLFW_KEY_PRINT_SCREEN;
    public const PAUSE = GLFW_KEY_PAUSE;
    public const F1 = GLFW_KEY_F1;
    public const F2 = GLFW_KEY_F2;
    public const F3 = GLFW_KEY_F3;
    public const F4 = GLFW_KEY_F4;
    public const F5 = GLFW_KEY_F5;
    public const F6 = GLFW_KEY_F6;
    public const F7 = GLFW_KEY_F7;
    public const F8 = GLFW_KEY_F8;
    public const F9 = GLFW_KEY_F9;
    public const F10 = GLFW_KEY_F10;
    public const F11 = GLFW_KEY_F11;
    public const F12 = GLFW_KEY_F12;
    public const F13 = GLFW_KEY_F13;
    public const F14 = GLFW_KEY_F14;
    public const F15 = GLFW_KEY_F15;
    public const F16 = GLFW_KEY_F16;
    public const F17 = GLFW_KEY_F17;
    public const F18 = GLFW_KEY_F18;
    public const F19 = GLFW_KEY_F19;
    public const F20 = GLFW_KEY_F20;
    public const F21 = GLFW_KEY_F21;
    public const F22 = GLFW_KEY_F22;
    public const F23 = GLFW_KEY_F23;
    public const F24 = GLFW_KEY_F24;
    public const F25 = GLFW_KEY_F25;
    public const KP_0 = GLFW_KEY_KP_0;
    public const KP_1 = GLFW_KEY_KP_1;
    public const KP_2 = GLFW_KEY_KP_2;
    public const KP_3 = GLFW_KEY_KP_3;
    public const KP_4 = GLFW_KEY_KP_4;
    public const KP_5 = GLFW_KEY_KP_5;
    public const KP_6 = GLFW_KEY_KP_6;
    public const KP_7 = GLFW_KEY_KP_7;
    public const KP_8 = GLFW_KEY_KP_8;
    public const KP_9 = GLFW_KEY_KP_9;
    public const KP_DECIMAL = GLFW_KEY_KP_DECIMAL;
    public const KP_DIVIDE = GLFW_KEY_KP_DIVIDE;
    public const KP_MULTIPLY = GLFW_KEY_KP_MULTIPLY;
    public const KP_SUBTRACT = GLFW_KEY_KP_SUBTRACT;
    public const KP_ADD = GLFW_KEY_KP_ADD;
    public const KP_ENTER = GLFW_KEY_KP_ENTER;
    public const KP_EQUAL = GLFW_KEY_KP_EQUAL;
    public const LEFT_SHIFT = GLFW_KEY_LEFT_SHIFT;
    public const LEFT_CONTROL = GLFW_KEY_LEFT_CONTROL;
    public const LEFT_ALT = GLFW_KEY_LEFT_ALT;
    public const LEFT_SUPER = GLFW_KEY_LEFT_SUPER;
    public const RIGHT_SHIFT = GLFW_KEY_RIGHT_SHIFT;
    public const RIGHT_CONTROL = GLFW_KEY_RIGHT_CONTROL;
    public const RIGHT_ALT = GLFW_KEY_RIGHT_ALT;
    public const RIGHT_SUPER = GLFW_KEY_RIGHT_SUPER;
    public const MENU = GLFW_KEY_MENU;
    public const LAST = GLFW_KEY_LAST;

    /**
     * Static lookup array for key names
     * 
     * @var array<int, string>|null
     */
    private static ?array $keyLookup = null;

    /**
     * Function to get the name of a key from its constant
     */
    public static function getName(int $keyConstant): string
    {
        if (self::$keyLookup === null) {
            self::initKeyLookup();
        }
        
        return self::$keyLookup[$keyConstant] ?? 'UNKNOWN';
    }

    /**
     * Initialize the key lookup array once
     */
    private static function initKeyLookup(): void
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        
        self::$keyLookup = [];
        foreach ($constants as $name => $value) {
            self::$keyLookup[$value] = $name;
        }
    }
}