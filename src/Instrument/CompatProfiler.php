<?php 

namespace VISU\Instrument;

/**
 * This profiler represents a backwards compatibilty / legacy profiler for 
 * mainly MacOS. MacOS dropped support for GL_TIMESTAMP queries and now just 
 * returns the result 0 wihtout any error. This profiler will use the blocking
 * glBeginQuery / glEndQuery calls instead. Which suck for performance, not being able 
 * to nest etc. But it's better than nothing.
 */
class CompatProfiler implements ProfilerInterface
{
    /**
     * Enable / disable the profiler
     */
    public bool $enabled = false;

    /**
     * The number of samples collected for each query.
     */
    public int $sampleCount = 16;

    /**
     * An array of query handles per scope
     * 
     * @var array<string, array<array<int, int>>
     */
    private array $queries = [];

    /**
     * The currently profiled scope
     * 
     * @var string|null
     */
    private ?string $currentScope = null;

    /**
     * Boolean if the current scopes shall mesure GPU time
     */
    private bool $currentScopeMeasuresGpuTime = false;

    /**
     * An array of scopes that have been started in the current frame
     * 
     * @var array<string>
     */
    private array $startedScopes = [];

    /**
     * An array of scopes that have been sampled within the current frame
     * This array is generated based on the "startedScopes" array when finalize() is called
     */
    private array $sampledScopes = [];

    /**
     * The CPU time when the current scope was started
     */
    private ?int $scopeCpuStart = null;

    /**
     * An array CPU time samples for each scope
     * 
     * @var array<string, array<int>>
     */
    private array $cpuTimeElapsed = [];

    /**
     * An array of CPU sample counts per scope
     * This is because every scope can have multiple samples per frame
     * 
     * @var array<string, int>
     */
    private array $cpuScopeSampleCount = [];

    /**
     * An array of GPU sample counts per scope
     * This is because every scope can have multiple samples per frame
     * 
     * @var array<string, int>
     */
    private array $gpuScopeSampleCount = [];

    /**
     * Because we can make multiple mesurements per frame, we store the individual CPU time samples here
     * They will be summed into the "cpuTimeElapsed" array when finalize() is called
     * 
     * @var array<string, array<int>>
     */
    private array $cpuTimeElapsedFrame = [];

    /**
     * An array of elapsed time samples for each scope
     * 
     * @var array<string, array<int>>
     */
    private array $gpuTimeElapsed = [];

    /**
     * An array of triangle samples for each scope
     * 
     * @var array<string, array<int>>
     */
    private array $gpuTriangles = [];

    /**
     * Returns an array of sampled scopes for the current frame
     */
    public function getSampledScopes() : array
    {
        return $this->sampledScopes;
    }

    /**
     * Returns the average CPU time for the given scope
     */
    public function getAverageCpuTime(string $scope) : float
    {
        if (!isset($this->cpuTimeElapsed[$scope]) || count($this->cpuTimeElapsed[$scope]) === 0) {
            return 0;
        }

        return array_sum($this->cpuTimeElapsed[$scope]) / count($this->cpuTimeElapsed[$scope]);
    }

    /**
     * Returns the average GPU time for the given scope
     */
    public function getAverageGpuTime(string $scope) : float
    {
        if (!isset($this->gpuTimeElapsed[$scope]) || count($this->gpuTimeElapsed[$scope]) === 0) {
            return 0;
        }

        return array_sum($this->gpuTimeElapsed[$scope]) / count($this->gpuTimeElapsed[$scope]);
    }

    /**
     * Returns the average triangle count for the given scope
     */
    public function getAverageTriangleCount(string $scope) : float
    {
        if (!isset($this->gpuTriangles[$scope]) || count($this->gpuTriangles[$scope]) === 0) {
            return 0;
        }

        return array_sum($this->gpuTriangles[$scope]) / count($this->gpuTriangles[$scope]);
    }

    /**
     * Returns the averages for each scope and metric
     */
    public function getAveragesPerScope() : array
    {
        $data = [];
        foreach($this->sampledScopes as $scope) {
            $data[$scope] = [
                'cpu' => $this->getAverageCpuTime($scope),
                'cpu_samples' => $this->cpuScopeSampleCount[$scope] ?? 0,
                'gpu' => $this->getAverageGpuTime($scope),
                'gpu_samples' => $this->gpuScopeSampleCount[$scope] ?? 0,
                'gpu_triangles' => $this->getAverageTriangleCount($scope)
            ];
        }

        return $data;
    }

    /**
     * Starts a profiling scope
     * 
     * @param string $scope
     * @param bool $gpu If true, the GPU time will be measured. Otherwise only the CPU time will be measured.
     */
    public function start(string $scope, bool $gpu = false) : void
    {
        // if the profiler is disabled do nothing
        if (!$this->enabled) {
            return;
        }

        if ($this->currentScope !== null) {
            throw new \RuntimeException("Cannot start a new scope while another scope '{$this->currentScope}' is still active");
        }

        $this->startedScopes[] = $scope;

        // start the query
        $this->currentScope = $scope;
        $this->scopeCpuStart = Clock::now64();

        // if we don't want to measure the GPU time, we are done
        $this->currentScopeMeasuresGpuTime = $gpu;
        if (!$gpu) return;

        // if the query is unkwnon create it
        if (!isset($this->queries[$scope])) {   
            $this->queries[$scope] = [];
        }

        // generate the query objects
        glGenQueries(2, $timeQuery, $triangleQuery);

        $this->queries[$scope][] = [
            GL_TIME_ELAPSED => $timeQuery, 
            GL_PRIMITIVES_GENERATED => $triangleQuery
        ];

        // begin the queries
        glBeginQuery(GL_TIME_ELAPSED, $timeQuery);
        glBeginQuery(GL_PRIMITIVES_GENERATED, $triangleQuery);
    }

    /**
     * Ends the current profiling scope
     */
    public function end(string $scope) : void
    {
        // if the profiler is disabled do nothing
        if (!$this->enabled) {
            return;
        }

        if ($this->currentScope !== $scope) {
            throw new \RuntimeException("Cannot end a scope that is not active");
        }

        // store the results
        $this->cpuTimeElapsedFrame[$scope][] = Clock::now64() - $this->scopeCpuStart;

        // reset the current scope
        $this->currentScope = null;
        $this->scopeCpuStart = null;
        
        // if we don't want to measure the GPU time, we are done
        if (!$this->currentScopeMeasuresGpuTime) return;

        // end the queries
        glEndQuery(GL_TIME_ELAPSED);
        glEndQuery(GL_PRIMITIVES_GENERATED);
    }

    /**
     * Finalizes the profiling and fetches the results from the GPU
     */
    public function finalize() : void
    {
        // if the profiler is disabled do nothing
        if (!$this->enabled) {
            return;
        }

        // sum the CPU samples for each scope
        foreach ($this->cpuTimeElapsedFrame as $scope => $samples) {
            $this->cpuScopeSampleCount[$scope] = count($samples);
            $this->cpuTimeElapsed[$scope][] = array_sum($samples);

            // ensure CPU samples are shifterd
            if (count($this->cpuTimeElapsed[$scope]) > $this->sampleCount) {
                array_shift($this->cpuTimeElapsed[$scope]);
            }
        }

        // reset the per frame CPU samples
        $this->cpuTimeElapsedFrame = [];

        // now fetch all GPU samples per scope, sum them and push them
        foreach ($this->queries as $scope => $queries) 
        {
            $scopeGPUTimeElapsed = 0;
            $scopeGPUTriangles = 0;

            foreach($queries as $querySample) {
                glGetQueryObjectui64v($querySample[GL_TIME_ELAPSED], GL_QUERY_RESULT, $gpuTimeElapsed);
                glGetQueryObjectui64v($querySample[GL_PRIMITIVES_GENERATED], GL_QUERY_RESULT, $gpuTriangles);

                $scopeGPUTimeElapsed += $gpuTimeElapsed;
                $scopeGPUTriangles += $gpuTriangles;

                glDeleteQueries(2, $querySample[GL_TIME_ELAPSED], $querySample[GL_PRIMITIVES_GENERATED]);
            }

            // n queries = n samples
            $this->gpuScopeSampleCount[$scope] = count($queries);

            // if the scope is not yet known, initialize it
            if (!isset($this->gpuTimeElapsed[$scope])) {
                $this->gpuTimeElapsed[$scope] = [];
                $this->gpuTriangles[$scope] = [];
            }

            // add the new sample
            $this->gpuTimeElapsed[$scope][] = $scopeGPUTimeElapsed;
            $this->gpuTriangles[$scope][] = $scopeGPUTriangles;

            // remove the oldest sample if we have too many
            if (count($this->gpuTimeElapsed[$scope]) > $this->sampleCount) {
                array_shift($this->gpuTimeElapsed[$scope]);
                array_shift($this->gpuTriangles[$scope]);
            }
        }

        // restet the queries
        $this->queries = [];

        // reset the started scopes
        $this->sampledScopes = $this->startedScopes;
        $this->startedScopes = [];
    }
}