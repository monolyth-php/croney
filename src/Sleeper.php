<?php

namespace Monolyth\Croney;

/**
 * Helper class to let the task manager sleep. Can be overrided for unit tests.
 * See README.md for an example.
 */
class Sleeper
{
    private int $currentTime;

    public function __construct()
    {
        $this->currentTime = strtotime(date('Y-m-d H:i:00'));
    }

    public function snooze(int $seconds) : void
    {
        sleep($seconds);
        $this->advanceInternalClock();
    }

    protected function advanceInternalClock() : void
    {
        $this->currentTime = strtotime('+1 minute', $this->currentTime);
    }

    public function getDate(string $format = 'Y-m-d H:i') : string
    {
        return date($format, $this->currentTime);
    }
}

