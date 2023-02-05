<?php 

namespace VISU\OS;

use VISU\OS\Exception\InputMappingException;
use VISU\Signals\Input\KeySignal;
use VISU\Signals\Input\MouseButtonSignal;
use VISU\Signals\Input\MouseClickSignal;

class InputActionMap
{
    /**
     * Did press states (Did press is true for one update)
     * 
     * @var array<string, bool>
     */
    private array $actionStateDidPress = [];

    /**
     * Did release states (Did release is true for one update)
     * 
     * @var array<string, bool>
     */
    private array $actionStateDidRelease = [];

    /**
     * The current state of the action
     * 
     * @var array<string, int>
     */
    private array $actionState = [];

    /**
     * Input action map
     * 
     * @var array<int, string>
     */
    private array $keyToAction = [];

    /**
     * Mouse click is not a real button, we simply 
     * define its value here
     */
    const MOUSE_CLICK_VIRT_BUTTON = 10600;

    /**
     * Binds the given action key to the given button
     * 
     * @param string $action 
     * @param int $button 
     * @return void 
     */
    public function bindButton(string $action, int $button) : void 
    {
        if (isset($this->keyToAction[$button])) {
            throw new InputMappingException("The button '$button' is already mapped to the action '{$this->keyToAction[$button]}', and cannot be mapped to the action '$action'");
        }

        $this->keyToAction[$button] = $action;
    }

    /**
     * Binds a mouse click to the given action
     * 
     * Why an extra method and not just use MouseButton::LEFT? Because a mouse click in VISU is when 
     * you press the button and release it not to far away from the origin where the press began.
     * 
     * This means the click event has no current state, or did press/did release states. A mouse click 
     * either happened or it didn't. To allow keybinding on it, we simply interpret a mouse click 
     * as the RELEASE of a virtual button.
     * 
     * @param string $action
     */
    public function bindMouseClick(string $action) : void
    {
        $this->bindButton($action, self::MOUSE_CLICK_VIRT_BUTTON);
    }

    /**
     * Imports the given array as bindings for the action map. 
     * The array is a map from <string: action> to <string: button>, the button string 
     * in this situation will be mapped to the GLFW key constant.
     * 
     * We use some prefixing to make it easier to use, for example:
     *    'jump' => '@Key::SPACE' <- @ prefix indicates a button binding
     *    'select' => '#click' <- # prefix indicates an event binding
     *    'movement_z' => '=Joystick::AXIS_1' <- = prefix indicates an axis binding
     * 
     * @param array<string, string> $map 
     * @return void 
     */
    public function importArrayMap(array $map) : void 
    {
        foreach ($map as $action => $button) 
        {
            $prefix = $button[0];

            // button binding
            if ($prefix === '@') 
            {
                $button = substr($button, 1);

                if (!defined($button)) {
                    // try with the VISU namespace prefix
                    $button = "VISU\\OS\\" . $button;
                    if (!defined($button)) {
                        throw new InputMappingException("The button '$button' is not defined, and cannot be mapped to the action '$action'");
                    }
                }
    
                // check if the button contant is an integer
                $button = constant($button);
                if (!is_int($button)) {
                    throw new InputMappingException("The button '$button' is not an integer, and cannot be mapped to the action '$action'");
                }
    
                $this->bindButton($action, $button);
            }
            // special event bindings
            else if ($prefix === '#') 
            {
                if ($button === '#click') {
                    $this->bindMouseClick($action);
                }
                else {
                    throw new InputMappingException("The event '$button' is not a valid event, and cannot be mapped to the action '$action'");
                }
            } 
            // axis bindings
            else if ($prefix === '=') 
            {
                throw new InputMappingException("Axis bindings are not yet implemented");
            } 
            else {
                throw new InputMappingException("The button '$button' is not prefixed with a valid prefix, and cannot be mapped to the action '$action'");
            }
        }
    }

    /**
     * Handles the given key signal to update internal action states
     * 
     * @param KeySignal $signal
     */
    public function handleKeySignal(KeySignal $signal) : void 
    {
        if (!isset($this->keyToAction[$signal->key])) {
            return;
        }

        $action = $this->keyToAction[$signal->key];

        if ($signal->action === GLFW_PRESS) {
            $this->actionStateDidPress[$action] = true;
        } else if ($signal->action === GLFW_RELEASE) {
            $this->actionStateDidRelease[$action] = true;
        }

        $this->actionState[$action] = $signal->action;

        // we consider the event handled from here 
        // and prevent it from propagating further
        $signal->stopPropagation();
    }

    /**
     * Handles Mouse click signals to update internal action states
     * 
     * @param MouseClickSignal $signal
     */
    public function handleMouseClickSignal(MouseClickSignal $signal) : void
    {
        if (!isset($this->keyToAction[self::MOUSE_CLICK_VIRT_BUTTON])) {
            return;
        }

        // just set the did release state to true
        // when an event is received
        $action = $this->keyToAction[self::MOUSE_CLICK_VIRT_BUTTON];
        $this->actionStateDidRelease[$action] = true;

        // we consider the event handled from here 
        // and prevent it from propagating further
        $signal->stopPropagation();
    }

    /**
     * Handles the given mouse button signal to update internal action states
     * 
     * @param MouseButtonSignal $signal
     */
    public function handleMouseButtonSignal(MouseButtonSignal $signal) : void
    {
        if (!isset($this->keyToAction[$signal->button])) {
            return;
        }

        $action = $this->keyToAction[$signal->button];

        if ($signal->action === GLFW_PRESS) {
            $this->actionStateDidPress[$action] = true;
        } else if ($signal->action === GLFW_RELEASE) {
            $this->actionStateDidRelease[$action] = true;
        }

        $this->actionState[$action] = $signal->action;

        // we consider the event handled from here 
        // and prevent it from propagating further
        $signal->stopPropagation();
    }


    /**
     * Resets the internal states to prepare for the next frame
     */
    public function reset() : void
    {
        $this->actionStateDidPress = [];
        $this->actionStateDidRelease = [];
    }

    /**
     * Returns true if the action button was pressed since the last update
     * 
     * @param string $action 
     * @return bool 
     */
    public function didButtonPress(string $action) : bool 
    {
        return $this->actionStateDidPress[$action] ?? false;
    }
    
    /**
     * Returns true if the action button was released since the last update
     * 
     * @param string $action 
     * @return bool 
     */
    public function didButtonRelease(string $action) : bool 
    {
        return $this->actionStateDidRelease[$action] ?? false;
    }

    /**
     * Returns true if the action button is currently pressed
     * 
     * @param string $action
     * @return bool
     */
    public function isButtonDown(string $action) : bool 
    {
        return ($this->actionState[$action] ?? 0) === GLFW_PRESS || ($this->actionState[$action] ?? 0) === GLFW_REPEAT;
    }

    /**
     * Returns true if the action button is currently released
     * 
     * @param string $action 
     * @return bool 
     */
    public function isButtonUp(string $action) : bool 
    {
        return ($this->actionState[$action] ?? 0) === GLFW_RELEASE;
    }
}