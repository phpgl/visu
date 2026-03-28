<?php

namespace VISU\Transpiler;

class UITranspiler
{
    /**
     * Transpiles a UI JSON file to a PHP render class.
     *
     * @param string $jsonPath Path to the source JSON file
     * @param string $className Short class name (e.g. "Hud")
     * @param string $namespace PHP namespace for the generated class
     * @return string Generated PHP source code
     */
    public function transpile(string $jsonPath, string $className, string $namespace = 'VISU\\Generated\\UI'): string
    {
        $json = file_get_contents($jsonPath);
        if ($json === false) {
            throw new \RuntimeException("Failed to read UI file: {$jsonPath}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in UI file: {$jsonPath}");
        }

        return $this->transpileArray($data, $className, $namespace, $jsonPath);
    }

    /**
     * Transpiles a UI data array to a PHP render class.
     *
     * @param array<string, mixed> $data
     * @return string Generated PHP source code
     */
    public function transpileArray(array $data, string $className, string $namespace = 'VISU\\Generated\\UI', ?string $sourcePath = null): string
    {
        $ctx = new TranspileContext();
        $ctx->requireUse('VISU\\FlyUI\\FlyUI');
        $ctx->requireUse('VISU\\UI\\UIDataContext');
        $ctx->requireUse('VISU\\Signal\\DispatcherInterface');

        $this->transpileNode($data, $ctx);

        return $this->generateClass($className, $namespace, $ctx, $sourcePath);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileNode(array $node, TranspileContext $ctx): void
    {
        $type = $node['type'] ?? '';

        match ($type) {
            'panel' => $this->transpilePanel($node, $ctx),
            'label' => $this->transpileLabel($node, $ctx),
            'button' => $this->transpileButton($node, $ctx),
            'progressbar' => $this->transpileProgressBar($node, $ctx),
            'checkbox' => $this->transpileCheckbox($node, $ctx),
            'select' => $this->transpileSelect($node, $ctx),
            'image' => $this->transpileImage($node, $ctx),
            'space' => $this->transpileSpace($node, $ctx),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpilePanel(array $node, TranspileContext $ctx): void
    {
        $padding = $this->buildPaddingCode($node['padding'] ?? null, $ctx);
        $layoutVar = '$l' . $ctx->nextEntityIndex();

        $ctx->addLine("{$layoutVar} = FlyUI::beginLayout({$padding});");

        $flow = $node['layout'] ?? 'column';
        if ($flow === 'row') {
            $ctx->requireUse('VISU\\FlyUI\\FUILayoutFlow');
            $ctx->addLine("{$layoutVar}->flow(FUILayoutFlow::horizontal);");
        }

        if (isset($node['spacing'])) {
            $ctx->addLine("{$layoutVar}->spacing({$this->exportFloat((float) $node['spacing'])});");
        }

        if (isset($node['backgroundColor'])) {
            $colorCode = $this->buildColorCode($node['backgroundColor'], $ctx);
            $ctx->addLine("{$layoutVar}->backgroundColor({$colorCode});");
        }

        // Sizing
        if (isset($node['width'])) {
            $ctx->addLine("{$layoutVar}->fixedWidth({$this->exportFloat((float) $node['width'])});");
        } else {
            $wMode = $node['horizontalSizing'] ?? 'fill';
            if ($wMode === 'fit') {
                $ctx->addLine("{$layoutVar}->horizontalFit();");
            }
        }

        if (isset($node['height'])) {
            $ctx->addLine("{$layoutVar}->fixedHeight({$this->exportFloat((float) $node['height'])});");
        } else {
            $hMode = $node['verticalSizing'] ?? 'fit';
            if ($hMode === 'fill') {
                $ctx->addLine("{$layoutVar}->verticalFill();");
            }
        }

        $ctx->addLine('');

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->transpileNode($child, $ctx);
            }
        }

        $ctx->addLine('FlyUI::end();');
        $ctx->addLine('');
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileLabel(array $node, TranspileContext $ctx): void
    {
        $text = $node['text'] ?? '';
        $textCode = $this->buildBindingCode($text, $ctx);
        $colorCode = isset($node['color']) ? ', ' . $this->buildColorCode($node['color'], $ctx) : '';

        $viewVar = '$v' . $ctx->nextComponentIndex();
        $ctx->addLine("{$viewVar} = FlyUI::text({$textCode}{$colorCode});");

        if (isset($node['fontSize'])) {
            $ctx->addLine("{$viewVar}->fontSize({$this->exportFloat((float) $node['fontSize'])});");
        }
        if (!empty($node['bold'])) {
            $ctx->addLine("{$viewVar}->bold();");
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileButton(array $node, TranspileContext $ctx): void
    {
        $label = $node['label'] ?? $node['text'] ?? 'Button';
        $labelCode = $this->buildBindingCode($label, $ctx);
        $event = $node['event'] ?? null;
        $eventData = $node['eventData'] ?? [];

        if ($event !== null) {
            $ctx->requireUse('VISU\\Signals\\UI\\UIEventSignal');
            $eventStr = $this->exportString($event);
            $dataStr = $this->exportArray($eventData);
            $ctx->addLine("FlyUI::button({$labelCode}, function () use (\$dispatcher): void {");
            $ctx->addLine("    \$dispatcher->dispatch('ui.event', new UIEventSignal({$eventStr}, {$dataStr}));");
            $ctx->addLine("});");
        } else {
            $ctx->addLine("FlyUI::button({$labelCode}, function (): void {});");
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileProgressBar(array $node, TranspileContext $ctx): void
    {
        $valueExpr = $node['value'] ?? '0';
        $valueCode = $this->buildValueCode($valueExpr, $ctx);
        $colorCode = isset($node['color']) ? ', ' . $this->buildColorCode($node['color'], $ctx) : '';

        $viewVar = '$pb' . $ctx->nextComponentIndex();
        $ctx->addLine("{$viewVar} = FlyUI::progressBar((float)({$valueCode}){$colorCode});");

        if (isset($node['height'])) {
            $ctx->addLine("{$viewVar}->height({$this->exportFloat((float) $node['height'])});");
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileCheckbox(array $node, TranspileContext $ctx): void
    {
        $text = $this->buildBindingCode($node['text'] ?? $node['label'] ?? '', $ctx);
        $id = $node['id'] ?? 'cb_' . ($node['text'] ?? '');
        $event = $node['event'] ?? null;

        $stateVar = '$cbState_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $id);
        $ctx->addLine("static {$stateVar} = false;");

        if ($event !== null) {
            $ctx->requireUse('VISU\\Signals\\UI\\UIEventSignal');
            $eventStr = $this->exportString($event);
            $idStr = $this->exportString($id);
            $ctx->addLine("FlyUI::checkbox({$text}, {$stateVar}, function (bool \$checked) use (\$dispatcher): void {");
            $ctx->addLine("    \$dispatcher->dispatch('ui.event', new UIEventSignal({$eventStr}, ['id' => {$idStr}, 'checked' => \$checked]));");
            $ctx->addLine("});");
        } else {
            $ctx->addLine("FlyUI::checkbox({$text}, {$stateVar});");
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileSelect(array $node, TranspileContext $ctx): void
    {
        $name = $node['name'] ?? $node['id'] ?? 'select';
        $options = $this->exportArray($node['options'] ?? []);
        $event = $node['event'] ?? null;

        $stateVar = '$selState_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $selected = isset($node['selected']) ? $this->exportString($node['selected']) : 'null';
        $ctx->addLine("static {$stateVar} = {$selected};");

        $nameStr = $this->exportString($name);

        if ($event !== null) {
            $ctx->requireUse('VISU\\Signals\\UI\\UIEventSignal');
            $eventStr = $this->exportString($event);
            $ctx->addLine("FlyUI::select({$nameStr}, {$options}, {$stateVar}, function (string \$selected) use (\$dispatcher): void {");
            $ctx->addLine("    \$dispatcher->dispatch('ui.event', new UIEventSignal({$eventStr}, ['name' => {$nameStr}, 'selected' => \$selected]));");
            $ctx->addLine("});");
        } else {
            $ctx->addLine("FlyUI::select({$nameStr}, {$options}, {$stateVar});");
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileImage(array $node, TranspileContext $ctx): void
    {
        $w = $this->exportFloat((float) ($node['width'] ?? 64));
        $h = $this->exportFloat((float) ($node['height'] ?? 64));
        $colorCode = isset($node['color']) ? $this->buildColorCode($node['color'], $ctx) : $this->buildColorCode('#808080', $ctx);

        $ctx->addLine("\$imgLayout = FlyUI::beginLayout();");
        $ctx->addLine("\$imgLayout->fixedWidth({$w})->fixedHeight({$h})->backgroundColor({$colorCode}, 4.0);");
        $ctx->addLine("FlyUI::end();");
    }

    /**
     * @param array<string, mixed> $node
     */
    private function transpileSpace(array $node, TranspileContext $ctx): void
    {
        if (isset($node['width'])) {
            $ctx->addLine("FlyUI::spaceX({$this->exportFloat((float) $node['width'])});");
        }
        if (isset($node['height'])) {
            $ctx->addLine("FlyUI::spaceY({$this->exportFloat((float) $node['height'])});");
        }
    }

    /**
     * Builds PHP code for a binding expression.
     * Converts "{path}" patterns to direct $ctx->get() calls.
     */
    private function buildBindingCode(string $text, TranspileContext $ctx): string
    {
        // Pure binding: "{economy.money}"
        if (preg_match('/^\{([^}]+)\}$/', $text, $m)) {
            return "\$ctx->get('{$m[1]}', '')";
        }

        // Mixed text with bindings: "Money: {economy.money}"
        if (preg_match_all('/\{([^}]+)\}/', $text, $matches)) {
            $parts = preg_split('/\{[^}]+\}/', $text) ?: [];
            $result = '';
            foreach ($parts as $i => $part) {
                if ($part !== '') {
                    $result .= ($result !== '' ? ' . ' : '') . $this->exportString($part);
                }
                if (isset($matches[1][$i])) {
                    $path = $matches[1][$i];
                    $getValue = "\$ctx->get('{$path}', '')";
                    $result .= ($result !== '' ? ' . ' : '') . $getValue;
                }
            }
            return $result;
        }

        return $this->exportString($text);
    }

    /**
     * Builds PHP code for a value binding (for progressbar values etc).
     */
    private function buildValueCode(mixed $valueExpr, TranspileContext $ctx): string
    {
        if (is_numeric($valueExpr)) {
            return $this->exportFloat((float) $valueExpr);
        }

        if (is_string($valueExpr) && preg_match('/^\{([^}]+)\}$/', $valueExpr, $m)) {
            return "\$ctx->get('{$m[1]}', 0)";
        }

        if (is_string($valueExpr)) {
            return $this->exportString($valueExpr);
        }

        return '0.0';
    }

    private function buildPaddingCode(mixed $padding, TranspileContext $ctx): string
    {
        if ($padding === null) {
            return 'null';
        }

        $ctx->requireUse('GL\\Math\\Vec4');

        if (is_numeric($padding)) {
            $p = $this->exportFloat((float) $padding);
            return "new Vec4({$p}, {$p}, {$p}, {$p})";
        }

        if (is_array($padding)) {
            $l = $this->exportFloat((float) ($padding[0] ?? $padding['left'] ?? 0));
            $r = $this->exportFloat((float) ($padding[1] ?? $padding['right'] ?? 0));
            $t = $this->exportFloat((float) ($padding[2] ?? $padding['top'] ?? 0));
            $b = $this->exportFloat((float) ($padding[3] ?? $padding['bottom'] ?? 0));
            return "new Vec4({$l}, {$r}, {$t}, {$b})";
        }

        return 'null';
    }

    private function buildColorCode(mixed $color, TranspileContext $ctx): string
    {
        $ctx->requireUse('GL\\VectorGraphics\\VGColor');

        if (is_string($color) && str_starts_with($color, '#')) {
            $hex = ltrim($color, '#');
            $r = $this->exportFloat(hexdec(substr($hex, 0, 2)) / 255.0);
            $g = $this->exportFloat(hexdec(substr($hex, 2, 2)) / 255.0);
            $b = $this->exportFloat(hexdec(substr($hex, 4, 2)) / 255.0);
            if (strlen($hex) >= 8) {
                $a = $this->exportFloat(hexdec(substr($hex, 6, 2)) / 255.0);
                return "VGColor::rgba({$r}, {$g}, {$b}, {$a})";
            }
            return "VGColor::rgb({$r}, {$g}, {$b})";
        }

        if (is_array($color)) {
            $r = $this->exportFloat((float) ($color[0] ?? 0));
            $g = $this->exportFloat((float) ($color[1] ?? 0));
            $b = $this->exportFloat((float) ($color[2] ?? 0));
            return "VGColor::rgb({$r}, {$g}, {$b})";
        }

        return 'VGColor::white()';
    }

    private function generateClass(string $className, string $namespace, TranspileContext $ctx, ?string $sourcePath): string
    {
        $uses = $ctx->getUseStatements();
        sort($uses);
        $useLines = implode("\n", array_map(fn(string $u) => "use {$u};", array_unique($uses)));
        $bodyLines = implode("\n", array_map(fn(string $l) => $l === '' ? '' : "        {$l}", $ctx->getLines()));

        $sourceComment = $sourcePath !== null
            ? "\n    /** Source: {$sourcePath} */\n"
            : "\n";

        return <<<PHP
<?php

/**
 * AUTO-GENERATED by VISU UITranspiler.
 * DO NOT EDIT — changes will be overwritten.
 */

namespace {$namespace};

{$useLines}

class {$className}
{{$sourceComment}
    public static function render(UIDataContext \$ctx, DispatcherInterface \$dispatcher): void
    {
{$bodyLines}
    }
}

PHP;
    }

    private function exportFloat(float $value): string
    {
        $s = (string) $value;
        if (!str_contains($s, '.') && !str_contains($s, 'E') && !str_contains($s, 'e')) {
            $s .= '.0';
        }
        return $s;
    }

    private function exportString(string $value): string
    {
        return "'" . addcslashes($value, "'\\") . "'";
    }

    /**
     * @param array<mixed> $value
     */
    private function exportArray(mixed $value): string
    {
        if (!is_array($value)) {
            return '[]';
        }
        if (array_is_list($value)) {
            $items = array_map(fn($v) => is_string($v) ? $this->exportString($v) : var_export($v, true), $value);
            return '[' . implode(', ', $items) . ']';
        }
        $items = [];
        foreach ($value as $k => $v) {
            $kStr = is_string($k) ? $this->exportString($k) : $k;
            $vStr = is_string($v) ? $this->exportString($v) : var_export($v, true);
            $items[] = "{$kStr} => {$vStr}";
        }
        return '[' . implode(', ', $items) . ']';
    }
}
