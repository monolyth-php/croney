<?php

use Monolyth\Croney\{ Scheduler, RunAt, Sleeper };
use Gentry\Gentry\Wrapper;

/** Tests for the Croney scheduler */
return function () : Generator {
    $scheduler = new Scheduler;

    $this->beforeEach(function () use ($scheduler) {
        (new Wrapper($scheduler))->overrideOptions([]);
    });

    /** We can set a job which will be processed */
    yield function () use ($scheduler) {
        $run = false;
        $scheduler['test'] = function () use (&$run) {
            $run = true;
        };
        (new Wrapper($scheduler))->process();
        assert($run === true);
    };

    /** A job scheduled for the future won't be run */
    yield function () use ($scheduler) {
        $run = false;
        $scheduler['test'] =
            #[RunAt("2300-01-01")]
            function () use (&$run) {
                $run = true;
            };
        ob_start();
        (new Wrapper($scheduler))->process();
        $output = trim(ob_get_clean());
        assert($run === false);
        assert($output === "Skipping test due to RunAt configuration.");
    };

    /** We can override duration - setting to 2 will cause process to run twice */
    yield function () use ($scheduler) {
        $run = 0;
        $scheduler = new class (2) extends Scheduler {
            public function __construct(int $duration)
            {
                parent::__construct($duration);
                $this->sleeper = new class () extends Sleeper {
                    public function snooze(int $seconds) : void
                    {
                        $this->advanceInternalClock();
                    }
                };
            }
        };
        $scheduler['test'] = function () use (&$run) {
            $run++;
        };
        (new Wrapper($scheduler))->process();
        assert($run === 2);
    };

    /** A job configured to only run on even minutes will be run once, even with duration=2 */
    yield function () use ($scheduler) {
        $run = 0;
        $scheduler = new class (2) extends Scheduler {
            public function __construct(int $duration)
            {
                parent::__construct($duration);
                $this->sleeper = new class () extends Sleeper {
                    public function snooze(int $seconds) : void
                    {
                        $this->advanceInternalClock();
                    }
                };
            }
        };
        $scheduler['test'] =
            #[RunAt("Y-m-d H:[0-5][02468]")]
            function () use (&$run) {
                $run++;
            };
        ob_start();
        (new Wrapper($scheduler))->process();
        $output = trim(ob_get_clean());
        assert($run === 1);
        assert($output === "Skipping test due to RunAt configuration.");
    };

    /** We can override options and get the currently set value */
    yield function () use ($scheduler) {
        $wrapped = new Wrapper($scheduler);
        $wrapped->overrideOptions(['--verbose']);
        $options = $wrapped->getOptions();
        assert($options->getOption('v') === 1);
    };

    /** An invokeable class passed as a string can be used as a task */
    yield function () use ($scheduler) {
        class Foo
        {
            public function __invoke()
            {
                print "I ran!";
            }
        }
        $scheduler['test'] = Foo::class;
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert($output === 'I ran!');
    };

    /** A random class method can be used as a task */
    yield function () use ($scheduler) {
        class Bar
        {
            public function test()
            {
                print "I ran!";
            }
        }
        $scheduler['test'] = [new Bar, 'test'];
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert($output === 'I ran!');
    };

    /** A named function can be used as a task */
    yield function () use ($scheduler) {
        function test()
        {
            print "I ran!";
        }
        $scheduler['test'] = 'test';
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert($output === 'I ran!');
    };
};

