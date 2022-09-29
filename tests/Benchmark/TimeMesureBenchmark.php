<?php

namespace VISU\Tests\Benchmark;

use GL\Math\Mat4;

/**
 * vendor/bin/phpbench run tests/Benchmark/TimeMesureBenchmark.php --report=benchmark
*/
class TimeMesureBenchmark
{
    /**
     * @Revs(100000)
     */
    public function benchMicrotimeFloat()
    {
        $t = 0;
        for ($i = 0; $i < 1000; $i++) {
            $t += microtime(true);
        }
    }

    /**
     * @Revs(100000)
     */
    public function benchHRTimeInt()
    {
        $t = 0;
        for ($i = 0; $i < 1000; $i++) {
            $t += hrtime(true);
        }
    }

    /**
     * @Revs(100000)
     */
    public function benchHRTimeSplit()
    {
        $s = 0;
        $n = 0;
        for ($i = 0; $i < 1000; $i++) {
            [$s1, $n1] = hrtime();
            $s += $s1;
            $n += $n1;
        }
    }
}
