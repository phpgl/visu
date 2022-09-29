<?php 

namespace VISU\Instrument;

/**
 * The clock provides utility methods for measuring and working with
 * high resolution time.
 */
class Clock
{
    /**
     * Returns the current time in nanoseconds.
     * 
     * @return ClockNanoseconds 
     */
    public static function now() : ClockNanoseconds
    {
        [$seconds, $nanoseconds] = hrtime();
        return new ClockNanoseconds($seconds, $nanoseconds);
    }

    /**
     * Returns the current time in nanoseconds as a single 64 bit integer.
     * 
     * @return int 
     */
    public static function now64() : int
    {
        return hrtime(true);
    }

    /**
     * Returns the difference between two timestamps in nanoseconds as a single 64bit integer.
     * Note this does only work for timestamps that are less than 2^63 nanoseconds apart.
     * 
     * @param ClockNanoseconds $t1 The first timestamp.
     * @param ClockNanoseconds $t2 The second timestamp.
     * 
     * @return int 
     */
    public static function diff64(ClockNanoseconds $t1, ClockNanoseconds $t2) : int
    {
        $t1 = $t1->seconds * 1000000000 + $t1->nanoseconds;
        $t2 = $t2->seconds * 1000000000 + $t2->nanoseconds;

        return abs($t2 - $t1);
    }

    /**
     * Returns the time difference between two timestamps in nanoseconds.
     * The returned value is always positive, meaning the order of the arguments does not matter.
     * 
     * @param ClockNanoseconds $t1 
     * @param ClockNanoseconds $t2 
     * 
     * @return ClockNanoseconds 
     */
    public static function diff(ClockNanoseconds $t1, ClockNanoseconds $t2) : ClockNanoseconds
    {
        // this is a horrible overly complicated way to do this
        // i got frustrated after 15 minutes of trying to get it right
        // please fix this
        if ($t1->seconds * 1000000000 + $t1->nanoseconds > $t2->seconds * 1000000000 + $t2->nanoseconds) {
            $tmp = $t1;
            $t1 = $t2;
            $t2 = $tmp;
        }
        
        $nanoseconds = $t2->nanoseconds - $t1->nanoseconds;
        $seconds = $t2->seconds - $t1->seconds;

        if ($nanoseconds < 0) {
            $nanoseconds += 1000000000;
            $seconds -= 1;
        }
        
        return new ClockNanoseconds($seconds, $nanoseconds);
    }

    /**
     * Sleep for the given amount of nanoseconds.
     * 
     * @param ClockNanoseconds $nanoseconds
     * 
     * @return void 
     */ 
    public static function sleep(ClockNanoseconds $nanoseconds) : void
    {
        time_nanosleep($nanoseconds->seconds, $nanoseconds->nanoseconds);
    }

    /**
     * Sleep for the given amount of nanoseconds using a single 64 bit integer.
     *
     * @param int $nanoseconds
     *
     * @return void
     */
    public static function sleep64(int $nanoseconds) : void
    {
        $seconds = (int) ($nanoseconds / 1000000000);
        $nanoseconds = $nanoseconds % 1000000000;
        time_nanosleep($seconds, $nanoseconds);
    }
}