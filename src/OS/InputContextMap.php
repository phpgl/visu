<?php 

namespace VISU\OS;

use VISU\OS\Exception\InputMappingException;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\KeySignal;
use VISU\Signals\Input\MouseButtonSignal;
use VISU\Signals\Input\MouseClickSignal;

class InputContextMap
{
    /**
     * Current actions map
     * Do not modify this property!
     */
    public InputActionMap $actions;

    /**
     * The action map that should be applied on the next update
     */
    private ?InputActionMap $nextUpdateActionsMap = null;

    /**
     * Array of InputActionMap instances bound to a context name
     * 
     * @var array<string, InputActionMap>
     */
    private array $registeredActionMaps = [];

    /**
     * The function id of the event handler
     */
    private int $eventHandlerIdInputKey = -1;
    private int $eventHandlerIdInputMouseButton = -1;
    private int $eventHandlerIdInputMouseClick = -1;

    /**
     * Constructor
     */
    public function __construct(private Dispatcher $dispatcher)
    {
        $this->actions = new InputActionMap(); // create a dummy action map 

        // register the relevent event handlers
        $this->eventHandlerIdInputKey = $this->dispatcher->register(Input::EVENT_KEY, [$this, 'handleKeySignal']);
        $this->eventHandlerIdInputMouseButton = $this->dispatcher->register(Input::EVENT_MOUSE_BUTTON, [$this, 'handleMouseButtonSignal']);
        $this->eventHandlerIdInputMouseClick = $this->dispatcher->register(Input::EVENT_MOUSE_CLICK, [$this, 'handleMouseClickSignal']);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->dispatcher->unregister(Input::EVENT_KEY, $this->eventHandlerIdInputKey);
        $this->dispatcher->unregister(Input::EVENT_MOUSE_BUTTON, $this->eventHandlerIdInputMouseButton);
        $this->dispatcher->unregister(Input::EVENT_MOUSE_CLICK, $this->eventHandlerIdInputMouseClick);
    }
    
    /**
     * Handles the given key signal to update internal action states
     * 
     * @param KeySignal $signal
     */
    public function handleKeySignal(KeySignal $signal) : void 
    {
        // forward the singals to the active action map
        $this->actions->handleKeySignal($signal);
    }

    /**
     * Handles Mouse click signals to update internal action states
     * 
     * @param MouseClickSignal $signal
     */
    public function handleMouseClickSignal(MouseClickSignal $signal) : void
    {
        $this->actions->handleMouseClickSignal($signal);
    }

    /**
     * Handles the given mouse button signal to update internal action states
     * 
     * @param MouseButtonSignal $signal
     */
    public function handleMouseButtonSignal(MouseButtonSignal $signal) : void
    {
        $this->actions->handleMouseButtonSignal($signal);
    }

    /**
     * Resets the internal action states
     */
    public function reset() : void
    {
        if ($this->nextUpdateActionsMap !== null) {
            $this->actions = $this->nextUpdateActionsMap;
            $this->nextUpdateActionsMap = null;
        }

        $this->actions->reset();
    }

    /**
     * Registers the given action map to the given context
     * 
     * @param string $context 
     * @param InputActionMap $actionMap
     */
    public function register(string $context, InputActionMap $actionMap) : void 
    {
        if (isset($this->registeredActionMaps[$context])) {
            throw new InputMappingException("Context '$context' is already registered");
        }

        $this->registeredActionMaps[$context] = $actionMap;
    }

    /**
     * Registers and activates the given action map to the given context
     */
    public function registerAndActivate(string $context, InputActionMap $actionMap) : void
    {
        $this->register($context, $actionMap);
        $this->switchTo($context);
    }

    /**
     * Switches to the action map bound to the given context
     * 
     * @param string $context
     * @return void 
     */
    public function switchTo(string $context) : void
    {
        if (!isset($this->registeredActionMaps[$context])) {
            throw new InputMappingException("Context '$context' is not registered");
        }

        $this->actions = $this->registeredActionMaps[$context];
    }

    /**
     * Switches to the action map bound to the given context on the next update.
     * This can be really helpful when you want to switch to a new context but 
     * you don't want to reset the current context until the next update, all action are done..
     * 
     * @param string $context
     * @return void 
     */
    public function switchToNext(string $context) : void
    {
        if (!isset($this->registeredActionMaps[$context])) {
            throw new InputMappingException("Context '$context' is not registered");
        }
        
        // do not switch if the current context is already the given context
        if ($this->registeredActionMaps[$context] === $this->actions) {
            return;
        }

        $this->nextUpdateActionsMap = $this->registeredActionMaps[$context];
    }
}