<?php

namespace Monolyth\Croney;

use InvalidArgumentException;
use Exception;
use ArrayObject;
use Monolog\Logger;

class Scheduler extends ArrayObject
{
    private $now;
    private $minutes = 1;
    private $jobs = [];
    private $logger;

    /**
     * Constructor. Optionally pass a Monolog\Logger.
     *
     * @param Monolyth\Logger|null $logger
     * @return void
     */
    public function __construct(Logger $logger = null)
    {
        set_time_limit(60);
        $this->now = strtotime(date('Y-m-d H:i:00'));
        $this->logger = isset($logger) ? $logger : new ErrorLogger;
    }

    /**
     * Getter. Only accepts `"logger"` as a property.
     *
     * @param string $property
     * @return Monolog\Logger|null
     */
    public function __get(string $property)
    {
        return $property == 'logger' ? $this->logger : null;
    }

    /**
     * Add a job to the schedule.
     *
     * @param string $name
     * @param callable $job The job.
     */
    public function offsetSet($name, $job)
    {
        if (!is_callable($job)) {
            throw new InvalidArgumentException('Each job must be callable');
        }
        $this->jobs[$name] = $job;
    }

    /**
     * Process the schedule and run all jobs which are due.
     *
     * @return void
     */
    public function process() : void
    {
        global $argv;
        $specific = null;
        foreach ($argv as $arg) {
            if (preg_match('@--job=(.*?)$@', $arg, $match)) {
                $specific = $match[1];
            }
        }
        $start = time();
        $tmp = sys_get_temp_dir();
        array_walk($this->jobs, function ($job, $idx) use ($tmp, $specific, $argv) {
            if (isset($specific) && $specific !== $idx) {
                return;
            }
            if (in_array('--verbose', $argv) || in_array('-v', $argv)) {
                echo "Starting $idx...";
            }
            $fp = fopen("$tmp/".md5($idx).'.lock', 'w+');
            flock($fp, LOCK_EX);
            try {
                $job->call($this);
            } catch (NotDueException $e) {
            } catch (Exception $e) {
                $this->logger->addCritial($e->getMessage());
            }
            flock($fp, LOCK_UN);
            fclose($fp);
            if (in_array('--verbose', $argv) || in_array('-v', $argv)) {
                echo " [done]\n";
            }
        });
        if (--$this->minutes) {
            $wait = max(60 - (time() - $start), 0);
            sleep($wait);
            $this->now += 60;
            $this->process();
        }
    }

    /**
     * The job should run "at" the specified time.
     *
     * @param string $datestring A string parsable by `date` that should match
     *  the current script runtime for the job to execute.
     * @return void
     * @throws Monolyth\Croney\NotDueException if the task isn't due yet.
     */
    public function at(string $datestring) : void
    {
        global $argv;
        if (in_array('--all', $argv) || in_array('-a', $argv)) {
            return;
        }
        $date = date($datestring, $this->now);
        if (!preg_match("@$date$@", date('Y-m-d H:i', $this->now))) {
            throw new NotDueException;
        }
    }

    /**
     * Set the number of minutes this process should run.
     *
     * All jobs are run every minute, hence setting this to '5' would cause the
     * loop to run 5 times. After each loop, the scheduler `sleep`s for sixty
     * seconds (minus the seconds it took the loop to run) before starting the
     * next run.
     *
     * Note that this does not guarantee the scheduler will resume _exactly_ on
     * the next minute. If your task involves handling based on e.g. `time()`,
     * make sure to round/truncate/check its value.
     *
     * @param int $minutes
     * @return void
     */
    public function setDuration(int $minutes) : void
    {
        $this->minutes = $minutes;
    }
}

