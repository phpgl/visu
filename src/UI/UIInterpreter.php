<?php

namespace VISU\UI;

use GL\Math\Vec4;
use GL\VectorGraphics\VGColor;
use VISU\FlyUI\FlyUI;
use VISU\FlyUI\FUILayoutFlow;
use VISU\Signal\DispatcherInterface;
use VISU\Signals\UI\UIEventSignal;

class UIInterpreter
{
    private UIDataContext $dataContext;

    /**
     * @var array<string, bool> Checkbox state storage
     */
    private array $checkboxStates = [];

    /**
     * @var array<string, string|null> Select state storage
     */
    private array $selectStates = [];

    public function __construct(
        private DispatcherInterface $dispatcher,
        ?UIDataContext $dataContext = null,
    ) {
        $this->dataContext = $dataContext ?? new UIDataContext();
    }

    public function getDataContext(): UIDataContext
    {
        return $this->dataContext;
    }

    public function setDataContext(UIDataContext $dataContext): void
    {
        $this->dataContext = $dataContext;
    }

    /**
     * Renders a UI layout from a JSON file.
     */
    public function renderFile(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("UI layout file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read UI layout file: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in UI layout file: {$path}");
        }

        $this->renderNode($data);
    }

    /**
     * Renders a UI layout from a data array.
     *
     * @param array<string, mixed> $node
     */
    public function renderNode(array $node): void
    {
        $type = UINodeType::tryFrom($node['type'] ?? '');
        if ($type === null) {
            return;
        }

        match ($type) {
            UINodeType::Panel => $this->renderPanel($node),
            UINodeType::Label => $this->renderLabel($node),
            UINodeType::Button => $this->renderButton($node),
            UINodeType::ProgressBar => $this->renderProgressBar($node),
            UINodeType::Checkbox => $this->renderCheckbox($node),
            UINodeType::Select => $this->renderSelect($node),
            UINodeType::Image => $this->renderImage($node),
            UINodeType::Space => $this->renderSpace($node),
        };
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderPanel(array $node): void
    {
        $padding = $this->parsePadding($node['padding'] ?? null);
        $layout = FlyUI::beginLayout($padding);

        $flowStr = $node['layout'] ?? 'column';
        if ($flowStr === 'row') {
            $layout->flow(FUILayoutFlow::horizontal);
        }

        if (isset($node['spacing'])) {
            $layout->spacing((float) $node['spacing']);
        }

        if (isset($node['backgroundColor'])) {
            $layout->backgroundColor($this->parseColor($node['backgroundColor']));
        }

        // Sizing
        if (isset($node['width'])) {
            $layout->fixedWidth((float) $node['width']);
        } else {
            $widthMode = $node['horizontalSizing'] ?? 'fill';
            if ($widthMode === 'fit') {
                $layout->horizontalFit();
            }
        }

        if (isset($node['height'])) {
            $layout->fixedHeight((float) $node['height']);
        } else {
            $heightMode = $node['verticalSizing'] ?? 'fit';
            if ($heightMode === 'fill') {
                $layout->verticalFill();
            }
        }

        // Render children
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->renderNode($child);
            }
        }

        FlyUI::end();
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderLabel(array $node): void
    {
        $text = $this->resolveText($node['text'] ?? '');
        $color = isset($node['color']) ? $this->parseColor($node['color']) : null;
        $view = FlyUI::text($text, $color);

        if (isset($node['fontSize'])) {
            $view->fontSize((float) $node['fontSize']);
        }
        if (isset($node['bold']) && $node['bold']) {
            $view->bold();
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderButton(array $node): void
    {
        $label = $this->resolveText($node['label'] ?? $node['text'] ?? 'Button');
        $event = $node['event'] ?? null;
        $eventData = $node['eventData'] ?? [];

        $dispatcher = $this->dispatcher;
        $button = FlyUI::button($label, function () use ($event, $eventData, $dispatcher): void {
            if ($event !== null) {
                $dispatcher->dispatch('ui.event', new UIEventSignal($event, $eventData));
            }
        });

        if (isset($node['id'])) {
            $button->setId((string) $node['id']);
        }
        if (isset($node['fullWidth']) && $node['fullWidth']) {
            $button->setFullWidth();
        }
        if (isset($node['style']) && $node['style'] === 'secondary') {
            $button->applyStyle(FlyUI::$instance->theme->secondaryButton);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderProgressBar(array $node): void
    {
        $valueExpr = $node['value'] ?? '0';
        $value = is_string($valueExpr) ? $this->dataContext->resolveValue($valueExpr) : $valueExpr;
        $value = is_numeric($value) ? (float) $value : 0.0;

        $color = isset($node['color']) ? $this->parseColor($node['color']) : null;
        $bar = FlyUI::progressBar($value, $color);

        if (isset($node['height'])) {
            $bar->height((float) $node['height']);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderCheckbox(array $node): void
    {
        $text = $this->resolveText($node['text'] ?? $node['label'] ?? '');
        $id = $node['id'] ?? 'cb_' . $text;
        $event = $node['event'] ?? null;

        // Persist checkbox state
        if (!isset($this->checkboxStates[$id])) {
            $bindingExpr = $node['checked'] ?? false;
            if (is_string($bindingExpr)) {
                $resolved = $this->dataContext->resolveValue($bindingExpr);
                $this->checkboxStates[$id] = (bool) $resolved;
            } else {
                $this->checkboxStates[$id] = (bool) $bindingExpr;
            }
        }

        $dispatcher = $this->dispatcher;
        FlyUI::checkbox($text, $this->checkboxStates[$id], function (bool $checked) use ($event, $id, $dispatcher): void {
            if ($event !== null) {
                $dispatcher->dispatch('ui.event', new UIEventSignal($event, ['id' => $id, 'checked' => $checked]));
            }
        });
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderSelect(array $node): void
    {
        $name = $node['name'] ?? $node['id'] ?? 'select_' . spl_object_id($this);
        $options = $node['options'] ?? [];
        $event = $node['event'] ?? null;

        if (!isset($this->selectStates[$name])) {
            $this->selectStates[$name] = $node['selected'] ?? null;
        }

        $dispatcher = $this->dispatcher;
        FlyUI::select($name, $options, $this->selectStates[$name], function (string $selected) use ($event, $name, $dispatcher): void {
            if ($event !== null) {
                $dispatcher->dispatch('ui.event', new UIEventSignal($event, ['name' => $name, 'selected' => $selected]));
            }
        });
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderImage(array $node): void
    {
        // Placeholder: render a colored rectangle with optional label
        $width = (float) ($node['width'] ?? 64);
        $height = (float) ($node['height'] ?? 64);
        $color = isset($node['color']) ? $this->parseColor($node['color']) : VGColor::rgb(0.5, 0.5, 0.5);

        $layout = FlyUI::beginLayout();
        $layout->fixedWidth($width)->fixedHeight($height)->backgroundColor($color, 4.0);
        FlyUI::end();
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderSpace(array $node): void
    {
        if (isset($node['width'])) {
            FlyUI::spaceX((float) $node['width']);
        }
        if (isset($node['height'])) {
            FlyUI::spaceY((float) $node['height']);
        }
    }

    private function resolveText(string $text): string
    {
        return $this->dataContext->resolveBindings($text);
    }

    private function parsePadding(mixed $padding): ?Vec4
    {
        if ($padding === null) {
            return null;
        }
        if (is_numeric($padding)) {
            $p = (float) $padding;
            return new Vec4($p, $p, $p, $p);
        }
        if (is_array($padding)) {
            return new Vec4(
                (float) ($padding[0] ?? $padding['left'] ?? 0),
                (float) ($padding[1] ?? $padding['right'] ?? 0),
                (float) ($padding[2] ?? $padding['top'] ?? 0),
                (float) ($padding[3] ?? $padding['bottom'] ?? 0),
            );
        }
        return null;
    }

    private function parseColor(mixed $color): VGColor
    {
        if ($color instanceof VGColor) {
            return $color;
        }
        if (is_string($color)) {
            // Hex color: #RRGGBB or #RRGGBBAA
            if (str_starts_with($color, '#')) {
                $hex = ltrim($color, '#');
                $r = hexdec(substr($hex, 0, 2)) / 255.0;
                $g = hexdec(substr($hex, 2, 2)) / 255.0;
                $b = hexdec(substr($hex, 4, 2)) / 255.0;
                $a = strlen($hex) >= 8 ? hexdec(substr($hex, 6, 2)) / 255.0 : 1.0;
                return VGColor::rgba((float) $r, (float) $g, (float) $b, (float) $a);
            }
        }
        if (is_array($color)) {
            return VGColor::rgb(
                (float) ($color[0] ?? 0),
                (float) ($color[1] ?? 0),
                (float) ($color[2] ?? 0),
            );
        }
        return VGColor::white();
    }

    /**
     * Returns the current checkbox state for a given ID.
     */
    public function getCheckboxState(string $id): bool
    {
        return $this->checkboxStates[$id] ?? false;
    }

    /**
     * Returns the current select state for a given name.
     */
    public function getSelectState(string $name): ?string
    {
        return $this->selectStates[$name] ?? null;
    }

    /**
     * Resets all widget states (checkbox, select, etc.).
     */
    public function resetStates(): void
    {
        $this->checkboxStates = [];
        $this->selectStates = [];
    }
}
