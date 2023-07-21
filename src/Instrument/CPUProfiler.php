<?php 

namespace VISU\Instrument;

class CPUProfiler implements ProfilerInterface
{
    /**
     * Enable / disable the profiler
     */
    public bool $enabled = false;

    /**
     * The number of samples collected for each query.
     */
    public int $sampleCount = 64;

    /**
     * An array of per scope recordings in nanoseconds
     * 
     * @var array<string, array<int>>
     */
    private array $recordings = [];

    /**
     * An array of per scope starting times
     * 
     * @var array<string, int>
     */
    private array $scopeCpuStart = [];

    /**
     * Starts a profiling scope
     * 
     * @param string $scope
     */
    public function start(string $scope) : void
    {
        // if the profiler is disabled do nothing
        if (!$this->enabled) {
            return;
        }

        if (isset($this->scopeCpuStart[$scope])) {
            throw new \RuntimeException("Scope $scope has already started, you need to stop it first");
        }

        $this->scopeCpuStart[$scope] = Clock::now64();
    }

    /**
     * Ends the current profiling scope
     */
    public function end(string $scope) : void
    {
        if (!$this->enabled) {
            return;
        }

        if (!isset($this->scopeCpuStart[$scope])) {
            throw new \RuntimeException("Scope $scope has not started, you need to start it first");
        }

        $duration = Clock::now64() - $this->scopeCpuStart[$scope];

        if (!isset($this->recordings[$scope])) {
            $this->recordings[$scope] = [];
        }

        $this->recordings[$scope][] = $duration;

        if (count($this->recordings[$scope]) > $this->sampleCount) {
            array_shift($this->recordings[$scope]);
        }
    }

    /**
     * Finalizes the profiling, cleans up internal state to ensure scopes are removed when 
     * not activley mesured anymore.
     */
    public function finalize() : void
    {
    }
}