<?php

namespace VISU\FlyUI;

class FUIPerformanceTracer
{
    /**
     * Cache of generated proxy class names mapped by original class name
     * 
     * @var array<string, string>
     */
    private static array $proxyClassCache = [];

    /**
     * Replaces the given view and all its children with tracing proxy views
     */
    public function replaceViewWithTracingProxy(FUIView $view) : FUIView
    {
        // create a subclass of the given view that acts as a proxy
        $reflectionClass = new \ReflectionClass($view);
        $className = $reflectionClass->getName();
        
        // check if we already have a cached proxy class for this type
        if (!isset(self::$proxyClassCache[$className])) {
            $proxyClassName = 'FUITracingProxy_' . str_replace('\\', '_', $className);

            if (!class_exists($proxyClassName, false)) {
                $classCode = $this->generateTracingProxyClass($proxyClassName, $className);
                eval($classCode);
            }

            self::$proxyClassCache[$className] = $proxyClassName;
        }

        // get the cached proxy class name
        $proxyClassName = self::$proxyClassCache[$className];
        
        // create a new instance of the proxy class without calling constructor
        /** @var FUIView $proxy */
        // @phpstan-ignore-next-line
        $proxy = (new \ReflectionClass($proxyClassName))->newInstanceWithoutConstructor();
        
        // copy all properties from the original view to the proxy
        $this->copyViewProperties($view, $proxy, $reflectionClass);
        
        // recursively replace all children with tracing proxies
        foreach ($proxy->children as $index => $child) {
            $proxy->children[$index] = $this->replaceViewWithTracingProxy($child);
        }
        
        return $proxy;
    }

    /**
     * Generates the proxy class code that wraps a view with performance tracing
     */
    private function generateTracingProxyClass(string $proxyClassName, string $originalClassName) : string
    {
        $escapedOriginalClassName = addslashes($originalClassName);
        
        return <<<PHP
class {$proxyClassName} extends {$originalClassName}
{
    public array \$tracingData = [];
    
    public function render(\VISU\FlyUI\FUIRenderContext \$ctx) : void
    {
        \$startTime = \VISU\Instrument\Clock::now64();
        
        try {
            parent::render(\$ctx);
        } finally {
            \$endTime = \VISU\Instrument\Clock::now64();
            \$duration = \$endTime - \$startTime;
            
            \$this->tracingData[] = [
                'method' => 'render',
                'class' => '{$escapedOriginalClassName}',
                'proxy_class' => '{$proxyClassName}',
                'duration_ns' => \$duration,
                'duration_ms' => \$duration / 1000000,
                'duration_us' => \$duration / 1000,
                'timestamp' => \$startTime,
                'object_id' => spl_object_id(\$this),
            ];
        }
    }
    
    public function getEstimatedSize(\VISU\FlyUI\FUIRenderContext \$ctx) : \GL\Math\Vec2
    {
        \$startTime = \VISU\Instrument\Clock::now64();
        
        try {
            return parent::getEstimatedSize(\$ctx);
        } finally {
            \$endTime = \VISU\Instrument\Clock::now64();
            \$duration = \$endTime - \$startTime;
            
            \$this->tracingData[] = [
                'method' => 'getEstimatedSize',
                'class' => '{$escapedOriginalClassName}',
                'proxy_class' => '{$proxyClassName}',
                'duration_ns' => \$duration,
                'duration_ms' => \$duration / 1000000,
                'duration_us' => \$duration / 1000,
                'timestamp' => \$startTime,
                'object_id' => spl_object_id(\$this),
            ];
        }
    }
}
PHP;
    }

    /**
     * Copies all properties from the source view to the target proxy
     * 
     * @param \ReflectionClass<FUIView> $reflectionClass
     */
    private function copyViewProperties(FUIView $source, object $target, \ReflectionClass $reflectionClass) : void
    {
        $properties = [];
        $currentClass = $reflectionClass;
        
        do {
            foreach ($currentClass->getProperties() as $property) {
                if (!$property->isStatic()) {
                    $properties[$property->getName()] = $property;
                }
            }
            $currentClass = $currentClass->getParentClass();
        } while ($currentClass !== false);
        
        // copy all property values
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($source);
            $property->setValue($target, $value);
        }
    }


    /**
     * Creates a performance trace snapshot from the given root view
     */
    public function getTrace(FUIView $rootView) : FUIPerformanceTrace
    {
        return new FUIPerformanceTrace($this->collectHierarchicalTracingData($rootView));
    }

    /**
     * Collects tracing data and builds hierarchical structure
     * 
     * @return array<string, mixed>
     */
    private function collectHierarchicalTracingData(FUIView $view) : array
    {
        $node = [
            'class_name' => get_class($view),
            'object_id' => spl_object_id($view),
            'methods' => [],
            'total_duration' => 0,
            'child_cost' => 0,
            'self_cost' => 0,
            'self_cost_percentage' => 0,
            'children' => [],
        ];
        
        // extract tracing data from this view if it's a proxy
        try {
            $reflection = new \ReflectionObject($view);
            if ($reflection->hasProperty('tracingData')) {
                $property = $reflection->getProperty('tracingData');
                $property->setAccessible(true);
                $tracingData = $property->getValue($view);
                if (is_array($tracingData)) {
                    $node['methods'] = $tracingData;
                    $node['total_duration'] = array_sum(array_column($tracingData, 'duration_ms'));

                    // we use the method class name as it reports the OG
                    if (isset($tracingData[0]['class'])) {
                        $node['class_name'] = $tracingData[0]['class'];
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // ignore reflection errors for non-proxy views
        }

        // recursively collect children and build hierarchy
        foreach ($view->children as $child) {
            $childNode = $this->collectHierarchicalTracingData($child);
            $node['children'][] = $childNode;
        }

        // calculate self cost (total time minus children time)
        $childrenDuration = array_sum(array_column($node['children'], 'total_duration'));
        $node['child_cost'] = $childrenDuration;
        $node['self_cost'] = max(0, $node['total_duration'] - $childrenDuration);
        $node['self_cost_percentage'] = $node['total_duration'] > 0 
            ? ($node['self_cost'] / $node['total_duration']) * 100 
            : 0;
        
        return $node;
    }

    /**
     * Clears the proxy class cache
     * 
     * This can be useful during development when view classes are being modified
     */
    public static function clearProxyCache() : void
    {
        self::$proxyClassCache = [];
    }
}