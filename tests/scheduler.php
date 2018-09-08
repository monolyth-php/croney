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
};

