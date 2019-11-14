<?php

namespace Monolyth\Croney;

use InvalidArgumentException;
use Exception;
use ArrayObject;
use Monolog\Logger;

trait Timing
{
    /** @var int */
    private $now;

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
}

