<?php

namespace Monolyth\Croney;

trait Duration
{
    /** @var int */
    private $minutes = 1;

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

