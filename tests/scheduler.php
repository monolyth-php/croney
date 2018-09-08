<?php

use Monolyth\Croney\Scheduler;

/** Tests for the Croney scheduler */
return function () : Generator {
    $scheduler = new Scheduler;

    /** We can set a job which will be processed */
    yield function () use ($scheduler) {
        $run = false;
        $scheduler['test'] = function () use (&$run) {
            $run = true;
        };
        $scheduler->process();
        assert($run === true);
    };

    /** A job scheduled for the future won't be run */
    yield function () use ($scheduler) {
        $run = false;
        $scheduler['test'] = function () use (&$run) {
            $this->at(date('Y-m-d H:i:s', strtotime('+1 day')));
            $run = true;
        };
        $scheduler->process();
        assert($run === false);
    };

    /** We can override duration - setting to i2 will cause process to run twice */
    yield function () use ($scheduler) {
        $run = 0;
        $scheduler['test'] = function () use (&$run) {
            $run++;
        };
        $scheduler->setDuration(2);
        $scheduler->process();
        assert($run === 2);
    };
};

