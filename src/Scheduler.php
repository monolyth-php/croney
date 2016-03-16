<?php

namespace Croney;

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

    public function __construct(Logger $logger = null)
    {
        $this->now = strtotime(date('Y-m-d H:i:00'));
        $this->logger = $logger;
    }

    /**
     * Add a job to the schedule.
     *
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
     */
    public function process()
    {
        $start = time();
        array_walk($this->jobs, function ($job, $idx) {
            try {
                $job->call($this);
            } catch (NotDueException $e) {
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->addCritial($e->getMessage());
                }
            }
        });
        if (--$this->minutes) {
            sleep(60 - (time() - $start));
            $this->now += 60;
            $this->process();
        }
    }

    /**
     * The job should run "at" the specified time.
     *
     * @param string $datestring A string parsable by `date` that should match
     *  the current script runtime for the job to execute.
     * @throws Croney\NotDueException if the task isn't due yet.
     */
    public function at($datestring)
    {
        $date = date('Y-m-d H:i:00', strtotime(date($datestring, $this->now)));
        if (!preg_match("@^$date$@", date('Y-m-d H:i:00', $this->now))) {
            throw new NotDueException;
        }
    }

    /**
     * Set the number of minutes this process should run.
     *
     * All jobs are run every minute, hence setting this to '5' would cause the
     * loop to run 5 times. After each loop, the scheduler `wait`s for sixty
     * seconds (minus the seconds it took the loop to run) before starting the
     * next run.
     *
     * @param int $minutes
     */
    public function setDuration($minutes)
    {
        if (!is_integer($minutes)) {
            throw new InvalidArgumentException('$minutes must be an integer.');
        }
        $this->minutes = $minutes;
    }
}

