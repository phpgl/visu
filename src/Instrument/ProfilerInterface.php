<?php 

namespace VISU\Instrument;

interface ProfilerInterface
{
    /**
     * Starts a profiling scope
     * 
     * @param string $scope A unique name for the scope
     * @param bool $gpu If true, the GPU metrics will be measured. Otherwise only the CPU time will be measured.
     */
    public function start(string $scope, bool $gpu = false) : void;

    /**
     * Ends the current profiling scope
     * 
     * @param string $scope The name of the scope to end
     */
    public function end(string $scope) : void;
}