<?php

namespace VISU\FlyUI;

use GL\Math\Vec2;
use GL\Math\Vec4;
use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use VISU\OS\Key;

class FUIPerformanceTracerOverlay extends FUIView
{
    /**
     * Maximum number of traces to keep in memory
     */
    private int $maxTraces;

    /**
     * Array of collected performance traces
     * @var array<FUIPerformanceTrace>
     */
    private array $traces = [];

    /**
     * Whether to display individual methods inside the tree view
     */
    private bool $showMethods = false;

    /**
     * Current view mode
     */
    private string $viewMode = 'summary';

    /**
     * Available view modes
     * 
     * @var array<string>
     */
    private array $viewModes = ['summary', 'tree', 'history'];

    /**
     * Background color for the overlay
     */
    private VGColor $backgroundColor;

    /**
     * Border color for the overlay
     */
    private VGColor $borderColor;

    /**
     * Text color for the overlay
     */
    private VGColor $textColor;

    /**
     * Current scroll offset for scrollable views
     */
    private float $scrollOffset = 0.0;

    /**
     * Height of each line in scrollable views
     */
    private float $lineHeight = 16.0;

    /**
     * Whether tracing is currently frozen (captured)
     */
    private bool $isFrozen = false;

    /**
     * Key Binding to toggle the overlay
     */
    public int $toggleKey = Key::F6;

    /**
     * Key Binding to switch to the next view mode
     */
    public int $nextViewModeKey = Key::TAB;

    /**
     * Key Binding for scrolling up
     */
    public int $scrollUpKey = Key::UP;

    /**
     * Key Binding for scrolling down
     */
    public int $scrollDownKey = Key::DOWN;

    /**
     * Key Binding for page up
     */
    public int $pageUpKey = Key::PAGE_UP;

    /**
     * Key Binding for page down
     */
    public int $pageDownKey = Key::PAGE_DOWN;

    /**
     * Key Binding for home
     */
    public int $homeKey = Key::HOME;

    /**
     * Key Binding for capture/freeze tracing
     */
    public int $captureKey = Key::C;

    /**
     * Key Binding to toggle method visibility inside the tree view
     */
    public int $toggleMethodsKey = Key::M;

    /**
     * Constructor
     */
    public function __construct(int $maxTraces = 50)
    {
        parent::__construct();
        
        $this->maxTraces = $maxTraces;
        
        // initialize colors with defaults
        $this->backgroundColor = new VGColor(0.0, 0.0, 0.0, 0.7);
        $this->borderColor = new VGColor(0.3, 0.3, 0.3, 1.0);
        $this->textColor = new VGColor(1.0, 1.0, 1.0, 1.0);
    }

    /**
     * Add a performance trace to the collection
     */
    public function addTrace(FUIPerformanceTrace $trace): void
    {
        // If tracing is frozen, discard new traces
        if ($this->isFrozen) {
            return;
        }

        if (!$trace->isEmpty()) {
            $this->traces[] = $trace;
            
            // remove oldest traces if we exceed the maximum
            while (count($this->traces) > $this->maxTraces) {
                array_shift($this->traces);
            }
        }
    }

    /**
     * Switch to the next view mode
     */
    public function nextViewMode(): void
    {
        $currentIndex = array_search($this->viewMode, $this->viewModes);
        if ($currentIndex === false) $currentIndex = 0;
        $currentIndex = (int)$currentIndex;
        $nextIndex = ($currentIndex + 1) % count($this->viewModes);
        $this->viewMode = $this->viewModes[$nextIndex];
        $this->scrollOffset = 0.0; // reset scroll when switching modes
    }

    /**
     * Switch to the previous view mode
     */
    public function previousViewMode(): void
    {
        $currentIndex = array_search($this->viewMode, $this->viewModes);
        if ($currentIndex === false) $currentIndex = 0;
        $currentIndex = (int)$currentIndex;
        $prevIndex = ($currentIndex - 1 + count($this->viewModes)) % count($this->viewModes);
        $this->viewMode = $this->viewModes[$prevIndex];
        $this->scrollOffset = 0.0; // reset scroll when switching modes
    }

    /**
     * Get the current view mode
     */
    public function getViewMode(): string
    {
        return $this->viewMode;
    }

    /**
     * Set the view mode
     */
    public function setViewMode(string $mode): void
    {
        if (in_array($mode, $this->viewModes)) {
            $this->viewMode = $mode;
            $this->scrollOffset = 0.0;
        }
    }

    /**
     * Scroll the view by the given amount
     */
    public function scroll(float $delta): void
    {
        $this->scrollOffset += $delta;
        $this->scrollOffset = max(0.0, $this->scrollOffset);
    }

    /**
     * Clear all collected traces
     */
    public function clearTraces(): void
    {
        $this->traces = [];
    }

    /**
     * Check if tracing is currently frozen
     */
    public function isFrozen(): bool
    {
        return $this->isFrozen;
    }

    /**
     * Toggle the frozen state of tracing
     */
    public function toggleFrozen(): void
    {
        $this->isFrozen = !$this->isFrozen;
    }

    /**
     * Set the frozen state of tracing
     */
    public function setFrozen(bool $frozen): void
    {
        $this->isFrozen = $frozen;
    }

    /**
     * Get the number of collected traces
     */
    public function getTraceCount(): int
    {
        return count($this->traces);
    }

    /**
     * Get the latest trace
     */
    public function getLatestTrace(): ?FUIPerformanceTrace
    {
        return empty($this->traces) ? null : end($this->traces);
    }

    /**
     * Calculate average performance metrics across all traces
     * 
     * @return array{totalTime: float, totalViews: float, renderCalls: float, count: int}
     */
    private function getAverageMetrics(): array
    {
        if (empty($this->traces)) {
            return [
                'totalTime' => 0.0,
                'totalViews' => 0.0,
                'renderCalls' => 0.0,
                'count' => 0
            ];
        }

        $totalTime = 0.0;
        $totalViews = 0.0;
        $totalRenderCalls = 0.0;
        $count = count($this->traces);

        foreach ($this->traces as $trace) {
            $totalTime += $trace->totalRenderTimeMs;
            $totalViews += $trace->totalViews;
            $totalRenderCalls += $trace->renderCalls;
        }

        return [
            'totalTime' => $totalTime / $count,
            'totalViews' => $totalViews / $count,
            'renderCalls' => $totalRenderCalls / $count,
            'count' => $count
        ];
    }

    /**
     * Calculate estimated size for the overlay
     */
    public function getEstimatedSize(FUIRenderContext $ctx): Vec2
    {
        return match ($this->viewMode) {
            'summary' => new Vec2($ctx->containerSize->x, 30),
            'tree', 'history' => new Vec2($ctx->containerSize->x * 0.9, $ctx->containerSize->y * 0.9),
            default => new Vec2(0, 0)
        };
    }

    /**
     * Render the overlay
     */
    public function render(FUIRenderContext $ctx): void
    {
        $pressedKeys = $ctx->input->getKeyPressesThisFrame();

        foreach ($pressedKeys as $key) {
            $this->handleKeyPress($key);
        }

        // the entire overlay uses monospace fonts
        $ctx->ensureMonospaceFontFace();

        switch ($this->viewMode) {
            case 'summary':
                $this->renderSummaryView($ctx);
                break;
            case 'tree':
                $this->renderTreeView($ctx);
                break;
            case 'history':
                $this->renderHistoryView($ctx);
                break;
        }
    }

    /**
     * Render the summary view (small bar at bottom)
     */
    private function renderSummaryView(FUIRenderContext $ctx): void
    {
        $averages = $this->getAverageMetrics();
        if ($averages['count'] === 0) {
            return;
        }

        $height = 30;
        $y = $ctx->containerSize->y - $height;

        // background
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->rect(0, $y, $ctx->containerSize->x, $height);
        $ctx->vg->fill();

        // border
        $ctx->vg->beginPath();
        $ctx->vg->strokeColor($this->borderColor);
        $ctx->vg->strokeWidth(1.0);
        $ctx->vg->moveTo(0, $y);
        $ctx->vg->lineTo($ctx->containerSize->x, $y);
        $ctx->vg->stroke();

        // text
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->fontSize(13);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);
        
        $avgTime = $averages['totalTime'];
        $avgViews = $averages['totalViews'];
        $avgRenderCalls = $averages['renderCalls'];
        $traceCount = $averages['count'];
        
        $summary = sprintf(
            "FlyUI Avg RT: %.2fms | %.1f views | %.1f calls | %d samples%s | [%s] to switch",
            $avgTime,
            $avgViews,
            $avgRenderCalls,
            $traceCount,
            $this->isFrozen ? ' | FROZEN' : '',
            Key::getName($this->nextViewModeKey)
        );

        $ctx->vg->text(10, $y + $height / 2, $summary);

        // performance bar visualization
        $barX = $ctx->containerSize->x - 200;
        $barY = $y + 8;
        $barWidth = 180;
        $barHeight = 14;

        // background bar
        $ctx->vg->beginPath();
        $ctx->vg->fillColor(new VGColor(0.2, 0.2, 0.2, 1.0));
        $ctx->vg->rect($barX, $barY, $barWidth, $barHeight);
        $ctx->vg->fill();

        // performance bar (red if over 16ms, yellow if over 8ms, green otherwise) - based on average
        $fillColor = $avgTime > 16.0 ? new VGColor(1.0, 0.2, 0.2, 1.0) :
                    ($avgTime > 8.0 ? new VGColor(1.0, 0.8, 0.2, 1.0) : new VGColor(0.2, 1.0, 0.2, 1.0));
        
        $fillWidth = min($barWidth, ($avgTime / 16.0) * $barWidth);
        
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($fillColor);
        $ctx->vg->rect($barX, $barY, $fillWidth, $barHeight);
        $ctx->vg->fill();

        // border around bar
        $ctx->vg->beginPath();
        $ctx->vg->strokeColor($this->borderColor);
        $ctx->vg->strokeWidth(1.0);
        $ctx->vg->rect($barX, $barY, $barWidth, $barHeight);
        $ctx->vg->stroke();
    }

    /**
     * Render the tree view (detailed overlay)
     */
    private function renderTreeView(FUIRenderContext $ctx): void
    {
        $latestTrace = $this->getLatestTrace();
        if (!$latestTrace) {
            $this->renderNoDataMessage($ctx);
            return;
        }

        $overlayWidth = $ctx->containerSize->x * 0.9;
        $overlayHeight = $ctx->containerSize->y * 0.9;
        $overlayX = ($ctx->containerSize->x - $overlayWidth) / 2;
        $overlayY = ($ctx->containerSize->y - $overlayHeight) / 2;

        // background
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->roundedRect($overlayX, $overlayY, $overlayWidth, $overlayHeight, 8);
        $ctx->vg->fill();

        // border
        $ctx->vg->beginPath();
        $ctx->vg->strokeColor($this->borderColor);
        $ctx->vg->strokeWidth(2.0);
        $ctx->vg->roundedRect($overlayX, $overlayY, $overlayWidth, $overlayHeight, 8);
        $ctx->vg->stroke();

        // header
        $headerHeight = 40;
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->fontSize(16);
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $ctx->vg->text($overlayX + $overlayWidth / 2, $overlayY + $headerHeight / 2, 
                      sprintf("Performance Tree View%s%s", $this->isFrozen ? ' (FROZEN)' : '', $this->showMethods ? ' - Methods' : ' - Views'));

        // controls text
        $ctx->vg->fontSize(10);
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::TOP);
        $ctx->vg->text($overlayX + $overlayWidth / 2, $overlayY + $headerHeight - 5, sprintf("[%s] Switch mode | [%s] Close | [↑/↓] Scroll | [%s] %s | [%s] Toggle methods", Key::getName($this->nextViewModeKey), Key::getName($this->toggleKey), Key::getName($this->captureKey), $this->isFrozen ? 'Unfreeze' : 'Freeze', Key::getName($this->toggleMethodsKey)));

        // content area
        $contentY = $overlayY + $headerHeight + 10;
        $contentHeight = $overlayHeight - $headerHeight - 20;
        
        // render the tree content
        $this->renderTreeContent($ctx, $latestTrace, $overlayX + 10, $contentY, $overlayWidth - 20, $contentHeight);
    }

    /**
     * Render the history view (list of all traces)
     */
    private function renderHistoryView(FUIRenderContext $ctx): void
    {
        if (empty($this->traces)) {
            $this->renderNoDataMessage($ctx);
            return;
        }

        $overlayWidth = $ctx->containerSize->x * 0.8;
        $overlayHeight = $ctx->containerSize->y * 0.8;
        $overlayX = ($ctx->containerSize->x - $overlayWidth) / 2;
        $overlayY = ($ctx->containerSize->y - $overlayHeight) / 2;

        // background
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->roundedRect($overlayX, $overlayY, $overlayWidth, $overlayHeight, 8);
        $ctx->vg->fill();

        // border
        $ctx->vg->beginPath();
        $ctx->vg->strokeColor($this->borderColor);
        $ctx->vg->strokeWidth(2.0);
        $ctx->vg->roundedRect($overlayX, $overlayY, $overlayWidth, $overlayHeight, 8);
        $ctx->vg->stroke();

        // header
        $headerHeight = 40;
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->fontSize(16);
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $ctx->vg->text($overlayX + $overlayWidth / 2, $overlayY + $headerHeight / 2, 
                      sprintf("Performance Graph (%d samples)%s", count($this->traces), $this->isFrozen ? ' (FROZEN)' : ''));

        // controls text
        $ctx->vg->fontSize(10);
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::TOP);
        $ctx->vg->text($overlayX + $overlayWidth / 2, $overlayY + $headerHeight - 5, sprintf("[%s] Switch mode | [%s] Close | [%s] %s", Key::getName($this->nextViewModeKey), Key::getName($this->toggleKey), Key::getName($this->captureKey), $this->isFrozen ? 'Unfreeze' : 'Freeze'));

        // content area
        $contentY = $overlayY + $headerHeight + 10;
        $contentHeight = $overlayHeight - $headerHeight - 20;
        
        $this->renderHistoryContent($ctx, $overlayX + 10, $contentY, $overlayWidth - 20, $contentHeight);
    }

    /**
     * Render tree content
     */
    private function renderTreeContent(FUIRenderContext $ctx, FUIPerformanceTrace $trace, float $x, float $y, float $width, float $height): void
    {
        $ctx->vg->fontSize(13);
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);

        $treeOutput = $trace->renderPerformanceTree($this->showMethods, 'timestamp');
        $lines = explode("\n", $treeOutput);
        
        $visibleLines = floor($height / $this->lineHeight);
        $startLine = floor($this->scrollOffset / $this->lineHeight);
        $endLine = min(count($lines), $startLine + $visibleLines);

        for ($i = $startLine; $i < $endLine; $i++) {
            if (isset($lines[$i])) {
                $lineY = $y + ($i - $startLine) * $this->lineHeight;
                
                // highlight lines with high self cost
                if (strpos($lines[$i], 'self:') !== false && preg_match('/self: (\d+\.\d+) ms/', $lines[$i], $matches)) {
                    $selfCost = (float)$matches[1];
                    if ($selfCost > 2.0) {
                        $ctx->vg->beginPath();
                        $ctx->vg->fillColor(new VGColor(1.0, 0.2, 0.2, 0.2));
                        $ctx->vg->rect($x, $lineY, $width, $this->lineHeight);
                        $ctx->vg->fill();
                    } elseif ($selfCost > 1.0) {
                        $ctx->vg->beginPath();
                        $ctx->vg->fillColor(new VGColor(1.0, 0.8, 0.2, 0.2));
                        $ctx->vg->rect($x, $lineY, $width, $this->lineHeight);
                        $ctx->vg->fill();
                    }
                }

                $ctx->vg->fillColor($this->textColor);
                $ctx->vg->text($x + 5, $lineY + 2, $lines[$i]);
            }
        }

        // scrollbar
        if (count($lines) > $visibleLines) {
            $scrollbarWidth = 8;
            $scrollbarX = $x + $width - $scrollbarWidth;
            $scrollbarHeight = max(20, ($visibleLines / count($lines)) * $height);
            $scrollbarY = $y + ($this->scrollOffset / (count($lines) * $this->lineHeight)) * ($height - $scrollbarHeight);

            $ctx->vg->beginPath();
            $ctx->vg->fillColor(new VGColor(0.5, 0.5, 0.5, 0.5));
            $ctx->vg->rect($scrollbarX, $scrollbarY, $scrollbarWidth, $scrollbarHeight);
            $ctx->vg->fill();
        }
    }

    /**
     * Render history content as a performance graph
     */
    private function renderHistoryContent(FUIRenderContext $ctx, float $x, float $y, float $width, float $height): void
    {
        if (empty($this->traces)) {
            return;
        }

        // graph area dimensions
        $graphX = $x + 40;
        $graphY = $y + 20;
        $graphWidth = $width - 80;
        $graphHeight = $height - 60;

        // calculate data ranges
        $maxTime = 0.0;
        
        foreach ($this->traces as $trace) {
            $maxTime = max($maxTime, $trace->totalRenderTimeMs);
        }

        // add some padding to the max values
        $maxTime = max(16.0, $maxTime * 1.1); // ensure at least 16ms for good scaling

        // draw background grid
        $this->drawGrid($ctx, $graphX, $graphY, $graphWidth, $graphHeight, $maxTime);

        // draw the performance lines
        $this->drawPerformanceLines($ctx, $graphX, $graphY, $graphWidth, $graphHeight, $maxTime);

        // draw axes and labels
        $this->drawAxes($ctx, $x, $y, $width, $height, $graphX, $graphY, $graphWidth, $graphHeight, $maxTime);

        // draw legend
        $this->drawLegend($ctx, $graphX + $graphWidth - 150, $graphY + 10);
    }

    /**
     * Draw background grid for the performance graph
     */
    private function drawGrid(FUIRenderContext $ctx, float $x, float $y, float $width, float $height, float $maxTime): void
    {
        $ctx->vg->strokeColor(new VGColor(0.3, 0.3, 0.3, 0.5));
        $ctx->vg->strokeWidth(1.0);

        // horizontal grid lines (time)
        $timeSteps = [1.0, 2.0, 4.0, 8.0, 16.0, 32.0, 64.0];
        foreach ($timeSteps as $timeStep) {
            if ($timeStep > $maxTime) break;
            
            $gridY = $y + $height - ($timeStep / $maxTime) * $height;
            
            $ctx->vg->beginPath();
            $ctx->vg->moveTo($x, $gridY);
            $ctx->vg->lineTo($x + $width, $gridY);
            $ctx->vg->stroke();
        }

        // vertical grid lines (samples)
        $sampleCount = count($this->traces);
        if ($sampleCount > 1) {
            $stepSize = max(1, floor($sampleCount / 10)); // roughly 10 vertical lines
            for ($i = 0; $i < $sampleCount; $i += $stepSize) {
                $gridX = $x + ($i / max(1, $sampleCount - 1)) * $width;
                
                $ctx->vg->beginPath();
                $ctx->vg->moveTo($gridX, $y);
                $ctx->vg->lineTo($gridX, $y + $height);
                $ctx->vg->stroke();
            }
        }
    }

    /**
     * Draw the performance data lines
     */
    private function drawPerformanceLines(FUIRenderContext $ctx, float $x, float $y, float $width, float $height, float $maxTime): void
    {
        $traceCount = count($this->traces);
        if ($traceCount < 2) return;

        // draw render time line (main metric)
        $ctx->vg->strokeWidth(3.0);
        $ctx->vg->strokeColor(new VGColor(1.0, 0.2, 0.2, 1.0)); // red for time
        $ctx->vg->beginPath();
        
        for ($i = 0; $i < $traceCount; $i++) {
            $trace = $this->traces[$i];
            $plotX = $x + ($i / max(1, $traceCount - 1)) * $width;
            $plotY = $y + $height - ($trace->totalRenderTimeMs / $maxTime) * $height;
            
            if ($i === 0) {
                $ctx->vg->moveTo($plotX, $plotY);
            } else {
                $ctx->vg->lineTo($plotX, $plotY);
            }
        }
        $ctx->vg->stroke();

        // draw performance threshold lines
        $this->drawThresholdLines($ctx, $x, $y, $width, $height, $maxTime);

        // draw data points for latest samples
        $this->drawDataPoints($ctx, $x, $y, $width, $height, $maxTime);
    }

    /**
     * Draw performance threshold indicator lines
     */
    private function drawThresholdLines(FUIRenderContext $ctx, float $x, float $y, float $width, float $height, float $maxTime): void
    {
        // 60fps line (16.67ms)
        if (16.67 <= $maxTime) {
            $ctx->vg->strokeWidth(1.0);
            $ctx->vg->strokeColor(new VGColor(1.0, 0.8, 0.0, 0.8)); // yellow warning line
            
            $thresholdY = $y + $height - (16.67 / $maxTime) * $height;
            $ctx->vg->beginPath();
            $ctx->vg->moveTo($x, $thresholdY);
            $ctx->vg->lineTo($x + $width, $thresholdY);
            $ctx->vg->stroke();
        }

        // 30fps line (33.33ms)
        if (33.33 <= $maxTime) {
            $ctx->vg->strokeWidth(1.0);
            $ctx->vg->strokeColor(new VGColor(1.0, 0.4, 0.0, 0.8)); // orange critical line
            
            $thresholdY = $y + $height - (33.33 / $maxTime) * $height;
            $ctx->vg->beginPath();
            $ctx->vg->moveTo($x, $thresholdY);
            $ctx->vg->lineTo($x + $width, $thresholdY);
            $ctx->vg->stroke();
        }
    }

    /**
     * Draw data points for recent samples
     */
    private function drawDataPoints(FUIRenderContext $ctx, float $x, float $y, float $width, float $height, float $maxTime): void
    {
        $traceCount = count($this->traces);
        $pointsToShow = min(10, $traceCount); // show last 10 points
        
        for ($i = max(0, $traceCount - $pointsToShow); $i < $traceCount; $i++) {
            $trace = $this->traces[$i];
            $plotX = $x + ($i / max(1, $traceCount - 1)) * $width;
            
            // time point (red)
            $timeY = $y + $height - ($trace->totalRenderTimeMs / $maxTime) * $height;
            $ctx->vg->beginPath();
            $ctx->vg->fillColor(new VGColor(1.0, 0.2, 0.2, 1.0));
            $ctx->vg->circle($plotX, $timeY, 3.0);
            $ctx->vg->fill();
            
            // highlight latest point
            if ($i === $traceCount - 1) {
                $ctx->vg->beginPath();
                $ctx->vg->strokeColor(new VGColor(1.0, 1.0, 1.0, 1.0));
                $ctx->vg->strokeWidth(2.0);
                $ctx->vg->circle($plotX, $timeY, 5.0);
                $ctx->vg->stroke();
            }
        }
    }

    /**
     * Draw axes and labels for the performance graph
     */
    private function drawAxes(FUIRenderContext $ctx, float $x, float $y, float $width, float $height, float $graphX, float $graphY, float $graphWidth, float $graphHeight, float $maxTime): void
    {
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->fontSize(10);

        // y-axis labels (time)
        $ctx->vg->textAlign(VGAlign::RIGHT | VGAlign::MIDDLE);
        $timeSteps = [0.0, 1.0, 2.0, 4.0, 8.0, 16.0, 32.0, 64.0];
        foreach ($timeSteps as $timeStep) {
            if ($timeStep > $maxTime) break;
            
            $labelY = $graphY + $graphHeight - ($timeStep / $maxTime) * $graphHeight;
            $ctx->vg->text($graphX - 5, $labelY, sprintf("%.0fms", $timeStep));
        }

        // x-axis labels (sample numbers)
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::TOP);
        $sampleCount = count($this->traces);
        if ($sampleCount > 1) {
            $stepSize = max(1, floor($sampleCount / 5)); // roughly 5 labels
            for ($i = 0; $i < $sampleCount; $i += $stepSize) {
                $labelX = $graphX + ($i / max(1, $sampleCount - 1)) * $graphWidth;
                $ctx->vg->text($labelX, $graphY + $graphHeight + 5, sprintf("#%d", $i + 1));
            }
        }

        // current values display
        if (!empty($this->traces)) {
            $latestTrace = end($this->traces);
            $ctx->vg->fontSize(12);
            $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::TOP);
            
            $currentX = $x + 10;
            $currentY = $y + $height - 20;
            
            $ctx->vg->fillColor(new VGColor(1.0, 0.2, 0.2, 1.0));
            $ctx->vg->text($currentX, $currentY, sprintf("Latest: %.2fms", $latestTrace->totalRenderTimeMs));
        }
    }

    /**
     * Draw legend for the performance graph
     */
    private function drawLegend(FUIRenderContext $ctx, float $x, float $y): void
    {
        $ctx->vg->fontSize(10);
        $ctx->vg->textAlign(VGAlign::LEFT | VGAlign::MIDDLE);

        // render time
        $ctx->vg->strokeWidth(3.0);
        $ctx->vg->strokeColor(new VGColor(1.0, 0.2, 0.2, 1.0));
        $ctx->vg->beginPath();
        $ctx->vg->moveTo($x, $y);
        $ctx->vg->lineTo($x + 20, $y);
        $ctx->vg->stroke();
        
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->text($x + 25, $y, "Render Time (ms)");
    }

    /**
     * Render a message when no data is available
     */
    private function renderNoDataMessage(FUIRenderContext $ctx): void
    {
        $overlayWidth = 400;
        $overlayHeight = 200;
        $overlayX = ($ctx->containerSize->x - $overlayWidth) / 2;
        $overlayY = ($ctx->containerSize->y - $overlayHeight) / 2;

        // background
        $ctx->vg->beginPath();
        $ctx->vg->fillColor($this->backgroundColor);
        $ctx->vg->roundedRect($overlayX, $overlayY, $overlayWidth, $overlayHeight, 8);
        $ctx->vg->fill();

        // border
        $ctx->vg->beginPath();
        $ctx->vg->strokeColor($this->borderColor);
        $ctx->vg->strokeWidth(2.0);
        $ctx->vg->roundedRect($overlayX, $overlayY, $overlayWidth, $overlayHeight, 8);
        $ctx->vg->stroke();

        // message
        $ctx->vg->fillColor($this->textColor);
        $ctx->vg->fontSize(14);
        $ctx->vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $ctx->vg->text($overlayX + $overlayWidth / 2, $overlayY + $overlayHeight / 2 - 10, "No performance data available");
        
        $ctx->vg->fontSize(10);
        $ctx->vg->text($overlayX + $overlayWidth / 2, $overlayY + $overlayHeight / 2 + 10, "Enable performance tracing and wait for data collection");
    }

    /**
     * Handle keyboard input for the overlay
     */
    public function handleKeyPress(int $key): bool
    {
        switch ($key) {
            case $this->nextViewModeKey:
                $this->nextViewMode();
                return true;
            case $this->scrollUpKey:
                $this->scroll(-$this->lineHeight * 3);
                return true;
            case $this->scrollDownKey:
                $this->scroll($this->lineHeight * 3);
                return true;
            case $this->pageUpKey:
                $this->scroll(-$this->lineHeight * 10);
                return true;
            case $this->pageDownKey:
                $this->scroll($this->lineHeight * 10);
                return true;
            case $this->homeKey:
                $this->scrollOffset = 0.0;
                return true;
            case $this->captureKey:
                $this->isFrozen = !$this->isFrozen;
                return true;
            case $this->toggleMethodsKey:
                $this->showMethods = !$this->showMethods;
                return true;
            case Key::P:
                
                // print performance summary to console
                $trace = $this->getLatestTrace();

                echo str_repeat("=", 80) . "\n";

                echo $trace?->renderPerformanceSummary();
                echo $trace?->renderSelfCostSummary();
                echo $trace?->renderPerformanceTree($this->showMethods);

                echo str_repeat("=", 80) . "\n";

                return true;
        }

        return false;
    }

    /**
     * Get help text for the current view mode
     */
    public function getHelpText(): string
    {
        $common = sprintf("[%s] Switch mode | [%s] Close", Key::getName($this->nextViewModeKey), Key::getName($this->toggleKey));
        
        return match ($this->viewMode) {
            'summary' => $common,
            'tree' => $common . sprintf(" | [↑/↓] Scroll | [%s] Toggle methods", Key::getName($this->toggleMethodsKey)),
            'history' => $common . sprintf(" | [%s] %s tracing", Key::getName($this->captureKey), $this->isFrozen ? 'Unfreeze' : 'Freeze'),
            default => $common
        };
    }

    /**
     * Set overlay colors
     */
    public function setColors(VGColor $background, VGColor $border, VGColor $text): void
    {
        $this->backgroundColor = $background;
        $this->borderColor = $border;
        $this->textColor = $text;
    }

    /**
     * Set the maximum number of traces to keep
     */
    public function setMaxTraces(int $maxTraces): void
    {
        $this->maxTraces = $maxTraces;
        
        // trim existing traces if necessary
        while (count($this->traces) > $this->maxTraces) {
            array_shift($this->traces);
        }
    }

    /**
     * Configure all keybindings for the overlay
     */
    public function setKeybindings(
        int $toggleKey = Key::F6,
        int $nextViewModeKey = Key::TAB,
        int $scrollUpKey = Key::UP,
        int $scrollDownKey = Key::DOWN,
        int $pageUpKey = Key::PAGE_UP,
        int $pageDownKey = Key::PAGE_DOWN,
        int $homeKey = Key::HOME,
        int $captureKey = Key::C,
        int $toggleMethodsKey = Key::M
    ): void {
        $this->toggleKey = $toggleKey;
        $this->nextViewModeKey = $nextViewModeKey;
        $this->scrollUpKey = $scrollUpKey;
        $this->scrollDownKey = $scrollDownKey;
        $this->pageUpKey = $pageUpKey;
        $this->pageDownKey = $pageDownKey;
        $this->homeKey = $homeKey;
        $this->captureKey = $captureKey;
        $this->toggleMethodsKey = $toggleMethodsKey;
    }
}