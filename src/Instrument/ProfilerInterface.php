<?php 

namespace VISU\Instrument;

interface ProfilerInterface
{
    /**
     * Starts a profiling scope
     * 
     * @param string $scope A unique name for the scope
     */
    public function start(string $scope) : void;

    /**
     * Ends the current profiling scope
     * 
     * @param string $scope The name of the scope to end
     */
    public function end(string $scope) : void;
}