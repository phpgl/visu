<?php

namespace VISU\Transpiler;

use VISU\ECS\ComponentRegistry;

class SceneTranspiler
{
    public function __construct(
        private ComponentRegistry $componentRegistry,
    ) {
    }

    /**
     * Transpiles a scene JSON file to a PHP factory class.
     *
     * @param string $jsonPath Path to the source JSON file
     * @param string $className Short class name (e.g. "OfficeLevel1")
     * @param string $namespace PHP namespace for the generated class
     * @return string Generated PHP source code
     */
    public function transpile(string $jsonPath, string $className, string $namespace = 'VISU\\Generated\\Scenes'): string
    {
        $json = file_get_contents($jsonPath);
        if ($json === false) {
            throw new \RuntimeException("Failed to read scene file: {$jsonPath}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in scene file: {$jsonPath}");
        }

        return $this->transpileArray($data, $className, $namespace, $jsonPath);
    }

    /**
     * Transpiles a scene data array to a PHP factory class.
     *
     * @param array<string, mixed> $data
     * @return string Generated PHP source code
     */
    public function transpileArray(array $data, string $className, string $namespace = 'VISU\\Generated\\Scenes', ?string $sourcePath = null): string
    {
        $entities = $data['entities'] ?? [];
        $context = new TranspileContext();

        foreach ($entities as $entityDef) {
            $this->transpileEntity($entityDef, $context, null);
        }

        return $this->generateClass($className, $namespace, $context, $sourcePath);
    }

    /**
     * @param array<string, mixed> $def
     */
    private function transpileEntity(array $def, TranspileContext $ctx, ?string $parentVar): void
    {
        $idx = $ctx->nextEntityIndex();
        $entityVar = '$e' . $idx;
        $transformVar = '$t' . $idx;

        $ctx->addLine("{$entityVar} = \$entities->create();");
        $ctx->addLine("\$ids[] = {$entityVar};");

        // Name
        if (isset($def['name'])) {
            $name = $this->exportString($def['name']);
            $ctx->addLine("\$entities->attach({$entityVar}, new NameComponent({$name}));");
            $ctx->requireUse('VISU\\Component\\NameComponent');
        }

        // Transform
        $this->transpileTransform($def['transform'] ?? [], $transformVar, $entityVar, $parentVar, $ctx);

        // Components
        foreach ($def['components'] ?? [] as $componentDef) {
            $this->transpileComponent($componentDef, $entityVar, $ctx);
        }

        // Children
        $ctx->addLine('');
        foreach ($def['children'] ?? [] as $childDef) {
            $this->transpileEntity($childDef, $ctx, $entityVar);
        }
    }

    /**
     * @param array<string, mixed> $def
     */
    private function transpileTransform(array $def, string $transformVar, string $entityVar, ?string $parentVar, TranspileContext $ctx): void
    {
        $ctx->requireUse('VISU\\Geo\\Transform');
        $ctx->requireUse('GL\\Math\\Vec3');

        $ctx->addLine("{$transformVar} = new Transform();");

        // Position
        $pos = $def['position'] ?? [0, 0, 0];
        $px = (float) ($pos[0] ?? $pos['x'] ?? 0);
        $py = (float) ($pos[1] ?? $pos['y'] ?? 0);
        $pz = (float) ($pos[2] ?? $pos['z'] ?? 0);
        if ($px !== 0.0 || $py !== 0.0 || $pz !== 0.0) {
            $ctx->addLine("{$transformVar}->position = new Vec3({$this->exportFloat($px)}, {$this->exportFloat($py)}, {$this->exportFloat($pz)});");
        }

        // Rotation (Euler degrees -> quaternion)
        if (isset($def['rotation'])) {
            $r = $def['rotation'];
            $rx = (float) ($r[0] ?? $r['x'] ?? 0);
            $ry = (float) ($r[1] ?? $r['y'] ?? 0);
            $rz = (float) ($r[2] ?? $r['z'] ?? 0);
            if ($rx !== 0.0 || $ry !== 0.0 || $rz !== 0.0) {
                $ctx->requireUse('GL\\Math\\GLM');
                $ctx->requireUse('GL\\Math\\Quat');
                $ctx->addLine("\$q{$transformVar} = new Quat();");
                if ($rx !== 0.0) {
                    $ctx->addLine("\$q{$transformVar}->rotate(GLM::radians({$this->exportFloat($rx)}), new Vec3(1, 0, 0));");
                }
                if ($ry !== 0.0) {
                    $ctx->addLine("\$q{$transformVar}->rotate(GLM::radians({$this->exportFloat($ry)}), new Vec3(0, 1, 0));");
                }
                if ($rz !== 0.0) {
                    $ctx->addLine("\$q{$transformVar}->rotate(GLM::radians({$this->exportFloat($rz)}), new Vec3(0, 0, 1));");
                }
                $ctx->addLine("{$transformVar}->orientation = \$q{$transformVar};");
            }
        }

        // Scale
        $scale = $def['scale'] ?? [1, 1, 1];
        $sx = (float) ($scale[0] ?? $scale['x'] ?? 1);
        $sy = (float) ($scale[1] ?? $scale['y'] ?? 1);
        $sz = (float) ($scale[2] ?? $scale['z'] ?? 1);
        if ($sx !== 1.0 || $sy !== 1.0 || $sz !== 1.0) {
            $ctx->addLine("{$transformVar}->scale = new Vec3({$this->exportFloat($sx)}, {$this->exportFloat($sy)}, {$this->exportFloat($sz)});");
        }

        // Parent
        if ($parentVar !== null) {
            $ctx->addLine("{$transformVar}->setParent(\$entities, {$parentVar});");
        }

        $ctx->addLine("{$transformVar}->markDirty();");
        $ctx->addLine("\$entities->attach({$entityVar}, {$transformVar});");
    }

    /**
     * @param array<string, mixed> $def
     */
    private function transpileComponent(array $def, string $entityVar, TranspileContext $ctx): void
    {
        $typeName = $def['type'] ?? null;
        if ($typeName === null) {
            return;
        }

        $fqcn = $this->componentRegistry->resolve($typeName);
        $ctx->requireUse($fqcn);

        $shortName = $this->shortClassName($fqcn);
        $idx = $ctx->nextComponentIndex();
        $componentVar = '$c' . $idx;

        $properties = $def;
        unset($properties['type']);

        $ctx->addLine("{$componentVar} = new {$shortName}();");

        foreach ($properties as $key => $value) {
            $exported = $this->exportValue($value);
            $ctx->addLine("{$componentVar}->{$key} = {$exported};");
        }

        $ctx->addLine("\$entities->attach({$entityVar}, {$componentVar});");
    }

    private function generateClass(string $className, string $namespace, TranspileContext $ctx, ?string $sourcePath): string
    {
        $uses = $ctx->getUseStatements();
        $uses[] = 'VISU\\ECS\\EntitiesInterface';
        sort($uses);

        $useLines = implode("\n", array_map(fn(string $u) => "use {$u};", array_unique($uses)));
        $bodyLines = implode("\n", array_map(fn(string $l) => $l === '' ? '' : "        {$l}", $ctx->getLines()));

        $sourceComment = $sourcePath !== null
            ? "\n    /** Source: {$sourcePath} */\n"
            : "\n";

        $escapedNs = str_replace('\\', '\\\\', $namespace);

        return <<<PHP
<?php

/**
 * AUTO-GENERATED by VISU SceneTranspiler.
 * DO NOT EDIT — changes will be overwritten.
 */

namespace {$namespace};

{$useLines}

class {$className}
{{$sourceComment}
    /**
     * @return array<int> Created entity IDs
     */
    public static function load(EntitiesInterface \$entities): array
    {
        \$ids = [];

{$bodyLines}

        return \$ids;
    }
}

PHP;
    }

    private function exportValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return $this->exportFloat($value);
        }
        if (is_string($value)) {
            return $this->exportString($value);
        }
        if (is_array($value)) {
            return $this->exportArray($value);
        }
        return var_export($value, true);
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
    private function exportArray(array $value): string
    {
        // Check if it's a sequential (list) array
        if (array_is_list($value)) {
            $items = array_map(fn($v) => $this->exportValue($v), $value);
            return '[' . implode(', ', $items) . ']';
        }

        // Associative array
        $items = [];
        foreach ($value as $k => $v) {
            $items[] = $this->exportValue($k) . ' => ' . $this->exportValue($v);
        }
        return '[' . implode(', ', $items) . ']';
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
