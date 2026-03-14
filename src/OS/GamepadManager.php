<?php

namespace VISU\OS;

use FFI;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Rendering\RenderContext;
use VISU\SDL3\SDL;
use VISU\Signal\DispatcherInterface;
use VISU\Signals\Gamepad\GamepadAxisSignal;
use VISU\Signals\Gamepad\GamepadButtonSignal;
use VISU\Signals\Gamepad\GamepadConnectionSignal;

class GamepadManager implements SystemInterface
{
    public const EVENT_AXIS       = 'gamepad.axis';
    public const EVENT_BUTTON     = 'gamepad.button';
    public const EVENT_CONNECTION = 'gamepad.connection';

    // SDL Sint16 range for normalisation
    private const AXIS_MAX = 32767.0;

    /** @var array<int, \FFI\CData> Map of SDL instance ID → SDL_Gamepad* */
    private array $openGamepads = [];

    /** @var array<int, int> Map of sequential gamepad index → SDL instance ID */
    private array $indexToId = [];

    public function __construct(
        private SDL $sdl,
        private DispatcherInterface $dispatcher,
    ) {}

    public function register(EntitiesInterface $entities): void
    {
        $this->refreshConnected();
    }

    public function unregister(EntitiesInterface $entities): void
    {
        foreach ($this->openGamepads as $gamepad) {
            $this->sdl->ffi->SDL_CloseGamepad($gamepad);
        }
        $this->openGamepads = [];
        $this->indexToId    = [];
    }

    public function update(EntitiesInterface $entities): void
    {
        while (($event = $this->sdl->pollEvent()) !== null) {
            $this->handleEvent($event);
        }
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }

    /**
     * Returns the normalised axis value in the range -1.0 .. 1.0.
     */
    public function getAxis(int $gamepadIndex, GamepadAxis $axis): float
    {
        $id      = $this->indexToId[$gamepadIndex] ?? null;
        $gamepad = $id !== null ? ($this->openGamepads[$id] ?? null) : null;
        if ($gamepad === null) {
            return 0.0;
        }
        $raw = $this->sdl->ffi->SDL_GetGamepadAxis($gamepad, $axis->value);
        return $raw / self::AXIS_MAX;
    }

    /**
     * Returns whether the given button is currently pressed.
     */
    public function isButtonPressed(int $gamepadIndex, GamepadButton $button): bool
    {
        $id      = $this->indexToId[$gamepadIndex] ?? null;
        $gamepad = $id !== null ? ($this->openGamepads[$id] ?? null) : null;
        if ($gamepad === null) {
            return false;
        }
        return (bool) $this->sdl->ffi->SDL_GetGamepadButton($gamepad, $button->value);
    }

    public function getConnectedCount(): int
    {
        return count($this->openGamepads);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $event
     */
    private function handleEvent(array $event): void
    {
        switch ($event['type']) {
            case SDL::EVENT_GAMEPAD_ADDED:
                $instanceId = $event['which'];
                $this->openGamepad($instanceId);
                break;

            case SDL::EVENT_GAMEPAD_REMOVED:
                $instanceId = $event['which'];
                $this->closeGamepad($instanceId);
                break;

            case SDL::EVENT_GAMEPAD_AXIS_MOTION:
                $instanceId = $event['which'];
                $index      = $this->getIndex($instanceId);
                if ($index === null) {
                    break;
                }
                $raw   = $event['value'];
                $value = $raw / self::AXIS_MAX;
                $axis  = GamepadAxis::tryFrom($event['axis']);
                if ($axis === null) {
                    break;
                }
                $this->dispatcher->dispatch(
                    self::EVENT_AXIS,
                    new GamepadAxisSignal($index, $axis, $value, $raw)
                );
                break;

            case SDL::EVENT_GAMEPAD_BUTTON_DOWN:
            case SDL::EVENT_GAMEPAD_BUTTON_UP:
                $instanceId = $event['which'];
                $index      = $this->getIndex($instanceId);
                if ($index === null) {
                    break;
                }
                $button = GamepadButton::tryFrom($event['button']);
                if ($button === null) {
                    break;
                }
                $this->dispatcher->dispatch(
                    self::EVENT_BUTTON,
                    new GamepadButtonSignal($index, $button, (bool) $event['down'])
                );
                break;
        }
    }

    private function openGamepad(int $instanceId): void
    {
        if (isset($this->openGamepads[$instanceId])) {
            return;
        }
        $gamepad = $this->sdl->ffi->SDL_OpenGamepad($instanceId);
        if ($gamepad === null) {
            return;
        }
        $this->openGamepads[$instanceId] = $gamepad;
        $this->rebuildIndex();

        $index = $this->getIndex($instanceId) ?? 0;
        $name  = FFI::string($this->sdl->ffi->SDL_GetGamepadName($gamepad));
        $this->dispatcher->dispatch(
            self::EVENT_CONNECTION,
            new GamepadConnectionSignal($index, true, $name)
        );
    }

    private function closeGamepad(int $instanceId): void
    {
        if (!isset($this->openGamepads[$instanceId])) {
            return;
        }
        $index = $this->getIndex($instanceId) ?? 0;
        $this->sdl->ffi->SDL_CloseGamepad($this->openGamepads[$instanceId]);
        unset($this->openGamepads[$instanceId]);
        $this->rebuildIndex();

        $this->dispatcher->dispatch(
            self::EVENT_CONNECTION,
            new GamepadConnectionSignal($index, false, '')
        );
    }

    private function refreshConnected(): void
    {
        $count    = $this->sdl->ffi->new('int');
        $ids      = $this->sdl->ffi->SDL_GetGamepads(FFI::addr($count));
        $numIds   = (int) $count->cdata;

        for ($i = 0; $i < $numIds; $i++) {
            $instanceId = $ids[$i];
            $this->openGamepad($instanceId);
        }

        if ($numIds > 0) {
            $this->sdl->ffi->SDL_free($ids);
        }
    }

    private function rebuildIndex(): void
    {
        $this->indexToId = array_values(array_keys($this->openGamepads));
        // Flip: index → instanceId
        $this->indexToId = array_flip($this->indexToId);
        // Now $this->indexToId[sequentialIndex] = instanceId
        $this->indexToId = array_flip(array_flip($this->indexToId));
    }

    private function getIndex(int $instanceId): ?int
    {
        $flip = array_flip(array_keys($this->openGamepads));
        return $flip[$instanceId] ?? null;
    }
}
