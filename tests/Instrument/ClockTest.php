<?php 

namespace VISU\Tests\Instrument;

use VISU\Instrument\Clock;
use VISU\Instrument\ClockNanoseconds;

class ClockTest extends \PHPUnit\Framework\TestCase
{
    public function testClockNow()
    {
        $now = Clock::now();
        $this->assertIsInt($now->seconds);
        $this->assertIsInt($now->nanoseconds);

        $this->assertGreaterThan(0, $now->seconds);
        $this->assertGreaterThan(0, $now->nanoseconds);

        $now2 = Clock::now();
        $this->assertGreaterThan($now->nanoseconds, $now2->nanoseconds);
    }

    public function testClockDiff64()
    {
        $t1 = new ClockNanoseconds(50, 100000);
        $t2 = new ClockNanoseconds(50, 200000);

        $diff = Clock::diff64($t1, $t2);
        $this->assertEquals(100000, $diff);

        $diff = Clock::diff64($t2, $t1);
        $this->assertEquals(100000, $diff);

        // test with second diff
        $t1 = new ClockNanoseconds(50, 100000);
        $t2 = new ClockNanoseconds(51, 200000);

        $diff = Clock::diff64($t1, $t2);
        $this->assertEquals(1000100000, $diff);

        $diff = Clock::diff64($t2, $t1);
        $this->assertEquals(1000100000, $diff);

        // test with second diff and negative nanoseconds
        $t1 = new ClockNanoseconds(50, 100000);
        $t2 = new ClockNanoseconds(51, 90000);

        $diff = Clock::diff64($t1, $t2);
        $this->assertEquals(999990000, $diff);
    }

    public function testClockDiff()
    {
        $t1 = new ClockNanoseconds(50, 100000);
        $t2 = new ClockNanoseconds(50, 200000);

        $diff = Clock::diff($t1, $t2);
        $this->assertEquals(100000, $diff->nanoseconds);
        $this->assertEquals(0, $diff->seconds);

        $diff = Clock::diff($t2, $t1);
        $this->assertEquals(100000, $diff->nanoseconds);
        $this->assertEquals(0, $diff->seconds);

        // test with second diff
        $t1 = new ClockNanoseconds(50, 100000);
        $t2 = new ClockNanoseconds(51, 200000);

        $diff = Clock::diff($t1, $t2);
        $this->assertEquals(100000, $diff->nanoseconds);
        $this->assertEquals(1, $diff->seconds);

        $diff = Clock::diff($t2, $t1);
        $this->assertEquals(100000, $diff->nanoseconds);
        $this->assertEquals(1, $diff->seconds);

        // test with second diff and negative nanoseconds
        $t1 = new ClockNanoseconds(50, 100000);
        $t2 = new ClockNanoseconds(51, 90000);

        $diff = Clock::diff($t1, $t2);
        $this->assertEquals(999990000, $diff->nanoseconds);
        $this->assertEquals(0, $diff->seconds);

        $diff = Clock::diff($t2, $t1);
        $this->assertEquals(999990000, $diff->nanoseconds);
        $this->assertEquals(0, $diff->seconds);
    }

    public function testSleep()
    {
        $t1 = Clock::now();
        Clock::sleep(new ClockNanoseconds(0, 50000000)); // 50ms
        $t2 = Clock::now();

        $diff = Clock::diff($t1, $t2);
        $this->assertGreaterThan(50000000, $diff->nanoseconds);
    }
}