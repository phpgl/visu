<?php

namespace VISU\FlyUI;

class FUIPerformanceTrace
{   
    private const LINE_LENGTH = 80;

    /**
     * The total render time in milliseconds of the full trace
     */
    public float $totalRenderTimeMs = 0.0;

    /**
     * The total number of unique views that were traced
     */
    public int $totalViews = 0;

    /**
     * The total number of method calls
     */
    public int $totalCalls = 0;

    /**
     * The number of render method calls
     */
    public int $renderCalls = 0;

    /**
     * The number of getEstimatedSize method calls
     */
    public int $sizeCalls = 0;

    /**
     * Constructor
     * 
     * @param array<string, mixed> $hierarchicalData
     */
    public function __construct(private array $hierarchicalData)
    {
        $this->totalRenderTimeMs = $this->calculateTotalDuration($this->hierarchicalData);
        $this->countViews($this->hierarchicalData, $this->totalViews);
        $this->countMethodCalls($this->hierarchicalData, $this->totalCalls);
        $this->countMethodCallsByType($this->hierarchicalData, 'render', $this->renderCalls);
        $this->countMethodCallsByType($this->hierarchicalData, 'getEstimatedSize', $this->sizeCalls);
    }

    /**
     * Get the hierarchical data
     * 
     * @return array<string, mixed>
     */
    public function getHierarchicalData(): array
    {
        return $this->hierarchicalData;
    }

    /**
     * Get the raw flat tracing data
     * 
     * @return array<array{method: string, class: string, proxy_class: string, duration_ns: int, duration_ms: float, duration_us: float, timestamp: int, object_id: int}>
     */
    public function getRawData(): array
    {
        $rawData = [];
        $this->flattenTreeToRawData($this->hierarchicalData, $rawData);
        return $rawData;
    }

    /**
     * Check if the trace contains any data
     */
    public function isEmpty(): bool
    {
        return empty($this->hierarchicalData) ||  empty($this->hierarchicalData['methods']);
    }

    /**
     * Recursively calculates total duration from hierarchical data
     * 
     * @param array<string, mixed> $node
     * @return float
     */
    private function calculateTotalDuration(array $node): float
    {
        $total = (float)($node['total_duration'] ?? 0.0);
        
        if ($total > 0.0) {
            return $total;
        }

        $childrenTotal = 0.0;
        foreach ($node['children'] as $child) {
            $childrenTotal += $this->calculateTotalDuration($child);
        }

        return $childrenTotal;
    }

    /**
     * Recursively counts views in hierarchical data
     * 
     * @param array<string, mixed> $node
     * @param int &$count
     */
    private function countViews(array $node, int &$count): void
    {
        if (!empty($node['methods'])) {
            $count++;
        }
        
        foreach ($node['children'] as $child) {
            $this->countViews($child, $count);
        }
    }

    /**
     * Recursively counts method calls in hierarchical data
     * 
     * @param array<string, mixed> $node
     * @param int &$count
     */
    private function countMethodCalls(array $node, int &$count): void
    {
        $count += count($node['methods'] ?? []);
        
        foreach ($node['children'] as $child) {
            $this->countMethodCalls($child, $count);
        }
    }

    /**
     * Recursively counts method calls of a specific type in hierarchical data
     * 
     * @param array<string, mixed> $node
     * @param string $methodType
     * @param int &$count
     */
    private function countMethodCallsByType(array $node, string $methodType, int &$count): void
    {
        $methods = $node['methods'] ?? [];
        $count += count(array_filter($methods, fn($m) => $m['method'] === $methodType));
        
        foreach ($node['children'] as $child) {
            $this->countMethodCallsByType($child, $methodType, $count);
        }
    }

    /**
     * Flattens hierarchical tree to raw tracing data
     * 
     * @param array<string, mixed> $node
     * @param array<array{method: string, class: string, proxy_class: string, duration_ns: int, duration_ms: float, duration_us: float, timestamp: int, object_id: int}> &$rawData
     */
    private function flattenTreeToRawData(array $node, array &$rawData): void
    {
        $methods = $node['methods'] ?? [];
        foreach ($methods as $method) {
            $rawData[] = $method;
        }
        
        foreach ($node['children'] as $child) {
            $this->flattenTreeToRawData($child, $rawData);
        }
    }

    /**
     * Renders a simple performance summary
     */
    public function renderPerformanceSummary(): string
    {
        if ($this->isEmpty()) {
            return "No performance tracing data available.\n";
        }

        $output = str_repeat("-", self::LINE_LENGTH) . "\n";
        $output .= "FlyUI Performance Summary\n";
        $output .= str_repeat("-", self::LINE_LENGTH) . "\n";
        
        // calculate totals
        $totalRenderTime = $this->totalRenderTimeMs;
        $totalViews = $this->totalViews;
        $renderCalls = $this->renderCalls;
        $sizeCalls = $this->sizeCalls;

        $output .= sprintf("Total time: %.3f ms\n", $totalRenderTime);
        $output .= sprintf("Views rendered: %d\n", $totalViews);
        $output .= sprintf("Render calls: %d\n", $renderCalls);
        $output .= sprintf("Size calculations: %d\n", $sizeCalls);
        $output .= sprintf("Avg time per view: %.3f ms\n", $totalRenderTime / max($totalViews, 1));
        
        // group by view type
        $viewStats = $this->collectViewTimes($this->hierarchicalData);
        
        // sort by time descending
        uasort($viewStats, static function(array $a, array $b) {
            return $b['time'] <=> $a['time'];
        });
        
        $output .= "\nTime by view type:\n";
        $output .= str_repeat("-", self::LINE_LENGTH) . "\n";
        
        foreach ($viewStats as $viewType => $stats) {
            $time = $stats['time'];
            $count = $stats['count'];
            $percentage = $totalRenderTime > 0.0 ? ($time / $totalRenderTime) * 100 : 0.0;
            $average = $count > 0 ? $time / $count : 0.0;
            $output .= sprintf("%-20s %3d views %.3f ms (%5.1f%%, avg %.3f ms)\n", $viewType, $count, $time, $percentage, $average);
        }
        
        return $output;
    }

    /**
     * Renders a text-based performance tree from the hierarchical data
     * 
     * @param bool $showMethods Whether to show individual methods (render/getEstimatedSize) or aggregate by view
     * @param string $sortBy Sort by 'duration' (total time), 'self_cost' (excluding children), or 'timestamp'
     * @return string The formatted performance tree
     */
    public function renderPerformanceTree(bool $showMethods = false, string $sortBy = 'timestamp'): string
    {
        if ($this->isEmpty()) {
            return "No performance tracing data available.\n";
        }

        $output = str_repeat("-", self::LINE_LENGTH) . "\n";
        $output .= "FlyUI Performance Tree\n";
        $output .= str_repeat("-", self::LINE_LENGTH) . "\n";
        
        // calculate totals
        $totalRenderTime = $this->totalRenderTimeMs;
        $totalViews = $this->totalViews;
        $totalCalls = $this->totalCalls;

        $output .= sprintf("Total render time: %.3f ms\n", $totalRenderTime);
        $output .= sprintf("Total views: %d\n", $totalViews);
        $output .= sprintf("Total method calls: %d\n", $totalCalls);
        $output .= str_repeat("-", self::LINE_LENGTH) . "\n";
        
        // sort the tree if needed
        $sortedTree = $this->hierarchicalData;
        $this->sortHierarchicalTree($sortedTree, $sortBy, $showMethods);
        
        $output .= $this->renderViewTreeNode($sortedTree, 0, '', true, $showMethods);
        
        return $output;
    }

    /**
     * Sorts hierarchical tree recursively
     * 
     * @param array<string, mixed> &$tree
     * @param string $sortBy
     * @param bool $showMethods
     */
    private function sortHierarchicalTree(array &$tree, string $sortBy, bool $showMethods): void
    {
        // sort children
        if (!empty($tree['children'])) {
            if ($sortBy === 'duration') {
                usort($tree['children'], function($a, $b) {
                    return $b['total_duration'] <=> $a['total_duration'];
                });
            } elseif ($sortBy === 'self_cost') {
                usort($tree['children'], function($a, $b) {
                    return $b['self_cost'] <=> $a['self_cost'];
                });
            } else {
                usort($tree['children'], function($a, $b) {
                    $aMethods = $a['methods'] ?? [];
                    $bMethods = $b['methods'] ?? [];
                    if (empty($aMethods) || empty($bMethods)) {
                        return $a['object_id'] <=> $b['object_id'];
                    }
                    $aTimestamp = min(array_column($aMethods, 'timestamp'));
                    $bTimestamp = min(array_column($bMethods, 'timestamp'));
                    return $aTimestamp <=> $bTimestamp;
                });
            }
            
            // recursively sort children
            foreach ($tree['children'] as &$child) {
                $this->sortHierarchicalTree($child, $sortBy, $showMethods);
            }
        }
        
        // sort methods within this node if showing methods
        if ($showMethods && !empty($tree['methods'])) {
            if ($sortBy === 'duration') {
                usort($tree['methods'], function($a, $b) {
                    return $b['duration_ms'] <=> $a['duration_ms'];
                });
            } else {
                usort($tree['methods'], function($a, $b) {
                    return $a['timestamp'] <=> $b['timestamp'];
                });
            }
        }
    }

    /**
     * Renders a performance summary focused on self costs
     * 
     * @return string The formatted self cost summary
     */
    public function renderSelfCostSummary(): string
    {
        if ($this->isEmpty()) {
            return "No performance tracing data available.\n";
        }
        
        $output = str_repeat("-", self::LINE_LENGTH) . "\n";
        $output .= "FlyUI Self Cost Analysis\n";
        $output .= str_repeat("-", self::LINE_LENGTH) . "\n";

        $totalRenderTime = $this->totalRenderTimeMs;
        $output .= sprintf("Total render time: %.3f ms\n", $totalRenderTime);
        $output .= str_repeat("-", self::LINE_LENGTH) . "\n";

        $output .= "Views sorted by self cost (excluding children):\n\n";
        
        // flatten the hierarchical tree to get all nodes
        $allNodes = [];
        $this->flattenTree($this->hierarchicalData, $allNodes);
        
        // sort by self cost descending
        usort($allNodes, function($a, $b) {
            return $b['self_cost'] <=> $a['self_cost'];
        });
        
        foreach ($allNodes as $i => $node) {
            if ($i >= 10) break; // show top 10
            
            $className = $node['class_name'] ?? 'UnknownClass';
            $shortName = substr($className, strrpos($className, '\\') + 1);
            $selfCost = $node['self_cost'] ?? 0;
            $selfPercentage = $node['self_cost_percentage'] ?? 0;
            $objectId = $node['object_id'] ?? 0;
            $globalPercentage = $totalRenderTime > 0 ? ($selfCost / $totalRenderTime) * 100 : 0;
            
            $output .= sprintf(
                "%2d. %-20s obj:%d %.3f ms (%.1f%% of self, %.1f%% of total)\n",
                $i + 1,
                $shortName,
                $objectId,
                $selfCost,
                $selfPercentage,
                $globalPercentage
            );
        }
        
        return $output;
    }

    /**
     * Renders a view tree node with proper tree-like indentation
     * 
     * @param array<string, mixed> $node
     * @param int $depth
     * @param string $prefix
     * @param bool $isLast
     * @param bool $showMethods
     * @return string
     */
    private function renderViewTreeNode(array $node, int $depth, string $prefix = '', bool $isLast = true, bool $showMethods = false): string
    {
        $output = "";
        
        $className = $node['class_name'] ?? 'UnknownClass';
        $shortClassName = substr($className, strrpos($className, '\\') + 1);
        $duration = $node['total_duration'] ?? 0;
        $selfCost = $node['self_cost'] ?? 0;
        $selfPercentage = $node['self_cost_percentage'] ?? 0;
        $objectId = $node['object_id'] ?? 0;
        
        // tree structure symbols
        $connector = $isLast ? "└── " : "├── ";
        $childPrefix = $isLast ? "    " : "│   ";
        
        // view header with both total and self cost
        $output .= sprintf(
            "%s%s%s (total: %.3f ms, self: %.3f ms [%.1f%%]) [obj:%d]\n",
            $prefix,
            $connector,
            $shortClassName,
            $duration,
            $selfCost,
            $selfPercentage,
            $objectId
        );
        
        $newPrefix = $prefix . $childPrefix;
        
        // show methods if enabled
        if ($showMethods && !empty($node['methods'])) {
            $methodCount = count($node['methods']);
            foreach ($node['methods'] as $i => $method) {
                $isLastMethod = $i === $methodCount - 1 && empty($node['children']);
                $methodConnector = $isLastMethod ? "└── " : "├── ";
                
                $output .= sprintf(
                    "%s%s%s() %.3f ms\n",
                    $newPrefix,
                    $methodConnector,
                    $method['method'],
                    $method['duration_ms']
                );
            }
        }
        
        // render children
        $childCount = count($node['children']);
        foreach ($node['children'] as $i => $child) {
            $isLastChild = $i === $childCount - 1;
            $output .= $this->renderViewTreeNode($child, $depth + 1, $newPrefix, $isLastChild, $showMethods);
        }
        
        return $output;
    }

    /**
     * Flattens a tree structure into an array of all nodes
     * 
     * @param array<string, mixed> $node
     * @param array<array<string, mixed>> &$result
     */
    private function flattenTree(array $node, array &$result): void
    {
        $result[] = $node;
        
        foreach ($node['children'] as $child) {
            $this->flattenTree($child, $result);
        }
    }

    /**
     * Collects view times from hierarchical tree
     * 
     * @param array<string, mixed> $tree
     * @return array<string, array{time: float, count: int}>
     */
    private function collectViewTimes(array $tree): array
    {
        /** @var array<string, array{time: float, count: int}> $stats */
        $stats = [];
        
        if (!empty($tree['class_name'])) {
            $className = $tree['class_name'];
            $shortName = substr($className, strrpos($className, '\\') + 1);
            $selfCost = (float)($tree['self_cost'] ?? 0.0);
            if (!isset($stats[$shortName])) {
                $stats[$shortName] = [
                    'time' => 0.0,
                    'count' => 0,
                ];
            }
            $stats[$shortName]['time'] += $selfCost;
            $stats[$shortName]['count']++;
        }
        
        if (!empty($tree['children'])) {
            foreach ($tree['children'] as $child) {
                $childStats = $this->collectViewTimes($child);
                foreach ($childStats as $class => $data) {
                    if (!isset($stats[$class])) {
                        $stats[$class] = [
                            'time' => 0.0,
                            'count' => 0,
                        ];
                    }
                    $stats[$class]['time'] += $data['time'];
                    $stats[$class]['count'] += $data['count'];
                }
            }
        }
        
        return $stats;
    }
}