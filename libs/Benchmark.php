<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 9/29/16
 * Time: 3:47 PM
 */

namespace PhCrawler;


class Benchmark
{
    protected static $benchmark_results = [];
    protected static $benchmark_start_times = [];
    protected static $benchmark_start_count = [];
    protected static $temporary_benchmarks = [];


    public static function start($identifier, $temporary_benchmark = false)
    {
        self::$benchmark_start_times[$identifier] = self::getmicrotime();

        if (isset(self::$benchmark_start_count[$identifier]))
            self::$benchmark_start_count[$identifier] = self::$benchmark_start_count[$identifier] + 1;
        else self::$benchmark_start_count[$identifier] = 1;

        if ($temporary_benchmark == true)
        {
            self::$temporary_benchmarks[$identifier] = true;
        }
    }

    public static function getCallCount($identifier)
    {
        return self::$benchmark_start_count[$identifier];
    }


    public static function stop($identifier)
    {
        if (isset(self::$benchmark_start_times[$identifier]))
        {
            $elapsed_time = self::getmicrotime() - self::$benchmark_start_times[$identifier];

            if (isset(self::$benchmark_results[$identifier])) self::$benchmark_results[$identifier] += $elapsed_time;
            else self::$benchmark_results[$identifier] = $elapsed_time;

            return $elapsed_time;
        }

        return null;
    }

    public static function getElapsedTime($identifier)
    {
        if (isset(self::$benchmark_results[$identifier]))
        {
            return self::$benchmark_results[$identifier];
        }
    }


    public static function reset($identifier)
    {
        if (isset(self::$benchmark_results[$identifier]))
        {
            self::$benchmark_results[$identifier] = 0;
        }
    }

    public static function resetAll($retain_benchmarks = array())
    {
        // If no benchmarks should be retained
        if (count($retain_benchmarks) == 0)
        {
            self::$benchmark_results = array();
            return;
        }

        // Else reset all benchmarks BUT the retain_benachmarks
        @reset(self::$benchmark_results);
        while (list($identifier) = @each(self::$benchmark_results))
        {
            if (!in_array($identifier, $retain_benchmarks))
            {
                self::$benchmark_results[$identifier] = 0;
            }
        }
    }

    public static function printAllBenchmarks($linebreak = "<br />")
    {
        @reset(self::$benchmark_results);
        while (list($identifier, $elapsed_time) = @each(self::$benchmark_results))
        {
            if (!isset(self::$temporary_benchmarks[$identifier])) echo $identifier.": ".$elapsed_time." sec" . $linebreak;
        }
    }

    /**
     * Returns all registered benchmark-results.
     *
     * @return array associative Array. The keys are the benchmark-identifiers, the values the benchmark-times.
     */
    public static function getAllBenchmarks()
    {
        $benchmarks = array();

        @reset(self::$benchmark_results);
        while (list($identifier, $elapsed_time) = @each(self::$benchmark_results))
        {
            if (!isset(self::$temporary_benchmarks[$identifier])) $benchmarks[$identifier] = $elapsed_time;
        }

        return $benchmarks;
    }

    /**
     * Returns the current time in seconds and milliseconds.
     *
     * @return float
     */
    public static function getmicrotime()
    {
        return microtime(true);
    }
}
