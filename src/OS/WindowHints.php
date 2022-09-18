<?php 

namespace VISU\OS;

use GLFWwindow;
use VISU\Exception\VISUException;

class WindowHints
{   
    /**
     * Window related hints
     */

    /**
     * GLFW_RESIZABLE
     * 
     * Should the user be able to resize the window?
     * If null, the glfw default value is used
     */
    public ?bool $resizable = null; 

    /**
     * GLFW_VISIBLE
     * 
     * Should the window be visible?
     * If null, the glfw default value is used
     */
    public ?bool $visible = null;
    
    /**
     * GLFW_DECORATED
     * 
     * Should the window have a menu bar (border, close button etc.)?
     * If null, the glfw default value is used
     */
    public ?bool $decorated = null;

    /**
     * GLFW_FOCUSED
     * 
     * Should the window be focused?
     * If null, the glfw default value is used
     */
    public ?bool $focused = null;

    /**
     * GLFW_AUTO_ICONIFY
     * 
     * Should the window be iconified when it loses focus?
     * If null, the glfw default value is used
     */
    public ?bool $autoIconify = null;

    /**
     * GLFW_FLOATING
     * 
     * Should the window be floating above other windows? This is also called topmost or always-on-top.
     * If null, the glfw default value is used
     */
    public ?bool $floating = null;

    /**
     * GLFW_MAXIMIZED
     * 
     * Should the window be maximized when created?
     * If null, the glfw default value is used
     */
    public ?bool $maximized = null;

    /**
     * GLFW_CENTER_CURSOR
     * 
     * Should the cursor be centered over the window when created?
     * If null, the glfw default value is used
     */
    public ?bool $centerCursor = null;

    /**
     * GLFW_TRANSPARENT_FRAMEBUFFER
     * 
     * Should the framebuffer be transparent?
     * If null, the glfw default value is used
     */
    public ?bool $transparentFramebuffer = null;

    /**
     * GLFW_FOCUS_ON_SHOW
     * 
     * Should the window be focused when it is shown?
     * If null, the glfw default value is used
     */
    public ?bool $focusOnShow = null;

    /**
     * GLFW_SCALE_TO_MONITOR
     * 
     * Should the window content scale be changed when the window is moved to a different monitor?
     * If null, the glfw default value is used
     */
    public ?bool $scaleToMonitor = null;

    /**
     * Framebuffer related hints
     */

    /**
     * GLFW_STEREO
     * 
     * Should the framebuffer be stereoscopic?
     * If null, the glfw default value is used. This is a hard constraint.
     */
    public ?bool $stereoscopic = null;

    /**
     * GLFW_SAMPLES
     * 
     * The number of samples to use for multisampling. Zero disables multisampling.
     * If null, the glfw default value is used.
     */
    public ?int $samples = null;

    /**
     * GLFW_SRGB_CAPABLE
     * 
     * Should the framebuffer be sRGB capable?
     * If null, the glfw default value is used.
     */
    public ?bool $sRGBCapable = null;

    /**
     * GLFW_DOUBLEBUFFER
     * 
     * Should the framebuffer be double buffered? You almost always want this enabled, which is the default.
     * If null, the glfw default value is used.
     */
    public ?bool $doubleBuffer = null;

    /**
     * Monitor related hints
     */

    /**
     * GLFW_REFRESH_RATE
     * 
     * The desired refresh rate for the fullscreen window, in Hz. This hint is ignored for windowed mode windows.
     * If GLFW_DONT_CARE is specified, the highest available refresh rate will be used.
     * If null, the glfw default value is used.
     */
    public ?int $refreshRate = null;

    /**
     * Context related hints
     */

    /**
     * GLFW_CLIENT_API
     * 
     * The client API to use. Possible values are GLFW_OPENGL_API, GLFW_OPENGL_ES_API and GLFW_NO_API.
     * If null, the glfw default value is used. 
     * This is a hard constraint.
     */ 
    public ?int $clientAPI = null;

    /**
     * GLFW_CONTEXT_CREATION_API
     * 
     * The context creation api that should be used. Possible values are GLFW_NATIVE_CONTEXT_API, GLFW_EGL_CONTEXT_API, GLFW_OSMESA_CONTEXT_API.
     * If null, the glfw default value is used.
     * This is a hard constraint.
     */
    public ?int $contextCreationAPI = null;

    /**
     * GLFW_CONTEXT_VERSION_MAJOR
     * 
     * The major version of the client API context to create. In PHP-GLFW this is by default 4.
     * If null, the glfw default value is used.
     * This is a hard constraint.
     */
    public ?int $contextVersionMajor = 4;

    /**
     * GLFW_CONTEXT_VERSION_MINOR
     * 
     * The minor version of the client API context to create. In PHP-GLFW this is by default 1.
     * If null, the glfw default value is used.
     * This is a hard constraint.
     */
    public ?int $contextVersionMinor = 1;
    
    /**
     * GLFW_OPENGL_FORWARD_COMPAT
     * 
     * Should the OpenGL context be forward compatible? In PHP-GLFW this is by default true.
     * If null, the glfw default value is used.
     */
    public ?bool $forwardCompatible = true;

    /**
     * GLFW_OPENGL_DEBUG_CONTEXT
     * 
     * Should the OpenGL context be a debug context?
     * If null, the glfw default value is used.
     */
    public ?bool $debugContext = null;

    /**
     * GLFW_OPENGL_PROFILE
     * 
     * The OpenGL profile to create the context for. Possible values are GLFW_OPENGL_ANY_PROFILE, GLFW_OPENGL_CORE_PROFILE and GLFW_OPENGL_COMPAT_PROFILE.
     * If null, the glfw default value is used.
     * In PHP-GLFW this is by default GLFW_OPENGL_CORE_PROFILE.
     */
    public ?int $profile = GLFW_OPENGL_CORE_PROFILE;

    /**
     * GLFW_CONTEXT_ROBUSTNESS
     * 
     * The robustness strategy to be used by the context. Possible values are GLFW_NO_ROBUSTNESS, GLFW_NO_RESET_NOTIFICATION and GLFW_LOSE_CONTEXT_ON_RESET.
     * If null, the glfw default value is used.
     */
    public ?int $robustness = null;

    /**
     * GLFW_CONTEXT_RELEASE_BEHAVIOR
     * 
     * The release behavior to be used by the context. Possible values are GLFW_ANY_RELEASE_BEHAVIOR, GLFW_RELEASE_BEHAVIOR_FLUSH and GLFW_RELEASE_BEHAVIOR_NONE.
     * If null, the glfw default value is used.
     */
    public ?int $releaseBehavior = null;

    /**
     * GLFW_CONTEXT_NO_ERROR
     * 
     * Should the context be created without error reporting?
     * If null, the glfw default value is used.
     */
    public ?bool $noError = null;

    /**
     * macOS specific hints
     */

    /**
     * GLFW_COCOA_RETINA_FRAMEBUFFER
     * 
     * Should the framebuffer be in Retina mode? This means the framebuffer will have the same resolution as the screen.
     * If null, the glfw default value is used.
     */
    public ?bool $cocoaRetinaFramebuffer = null;

    /**
     * GLFW_COCOA_FRAME_NAME
     * 
     * The name of the window frame. If null, the glfw default value is used.
     */
    public ?string $cocoaframeName = null;

    /**
     * GLFW_COCOA_GRAPHICS_SWITCHING
     * 
     * This allows the context to be used by multiple GPUs simultaneously. This is useful for laptops with both an integrated and a discrete GPU.
     * If null, the glfw default value is used.
     */
    public ?bool $cocoaGraphicsSwitching = null;

    /**
     * Returns an array of all available window hints.
     * 
     * @return array<int, mixed> 
     */
    public function getHintConstantMap() : array
    {
        return [
            GLFW_RESIZABLE => $this->resizable,
            GLFW_VISIBLE => $this->visible,
            GLFW_DECORATED => $this->decorated,
            GLFW_FOCUSED => $this->focused,
            GLFW_AUTO_ICONIFY => $this->autoIconify,
            GLFW_FLOATING => $this->floating,
            GLFW_MAXIMIZED => $this->maximized,
            GLFW_CENTER_CURSOR => $this->centerCursor,
            GLFW_TRANSPARENT_FRAMEBUFFER => $this->transparentFramebuffer,
            GLFW_FOCUS_ON_SHOW => $this->focusOnShow,
            GLFW_SCALE_TO_MONITOR => $this->scaleToMonitor,
            GLFW_SAMPLES => $this->samples,
            GLFW_REFRESH_RATE => $this->refreshRate,
            GLFW_STEREO => $this->stereoscopic,
            GLFW_SRGB_CAPABLE => $this->sRGBCapable,
            GLFW_DOUBLEBUFFER => $this->doubleBuffer,
            GLFW_CLIENT_API => $this->clientAPI,
            GLFW_CONTEXT_CREATION_API => $this->contextCreationAPI,
            GLFW_CONTEXT_VERSION_MAJOR => $this->contextVersionMajor,
            GLFW_CONTEXT_VERSION_MINOR => $this->contextVersionMinor,
            GLFW_OPENGL_FORWARD_COMPAT => $this->forwardCompatible,
            GLFW_OPENGL_DEBUG_CONTEXT => $this->debugContext,
            GLFW_OPENGL_PROFILE => $this->profile,
            GLFW_CONTEXT_ROBUSTNESS => $this->robustness,
            GLFW_CONTEXT_RELEASE_BEHAVIOR => $this->releaseBehavior,
            GLFW_CONTEXT_NO_ERROR => $this->noError,
            GLFW_COCOA_RETINA_FRAMEBUFFER => $this->cocoaRetinaFramebuffer,
            GLFW_COCOA_FRAME_NAME => $this->cocoaframeName,
            GLFW_COCOA_GRAPHICS_SWITCHING => $this->cocoaGraphicsSwitching,
        ];
    }


    /**
     * Applies the hints to the window.
     * This will execute the glfwWindowHint function for each hint that is not null.
     */
    public function apply() : void
    {
        $hints = $this->getHintConstantMap();

        foreach ($hints as $hint => $value) {

            if ($value !== null) 
            {
                if (is_bool($value)) {
                    glfwWindowHint($hint, $value ? GLFW_TRUE : GLFW_FALSE);
                }
                elseif (is_int($value)) {
                    glfwWindowHint($hint, $value);
                }
                elseif (is_string($value)) {
                    glfwWindowHintString($hint, $value);
                }
                else {
                    throw new VISUException("Invalid hint value type: " . gettype($value) . " for hint: " . $hint);
                }
            }
        }
    }
}  