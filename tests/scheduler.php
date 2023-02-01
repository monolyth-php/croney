<?php

use Monolyth\Croney\{ Scheduler, RunAt, Sleeper, TestLogger };
use Gentry\Gentry\Wrapper;
use Psr\Log\LoggerInterface;

/** Tests for the Croney scheduler */
return function () : Generator {
    $scheduler = new Wrapper(new Scheduler(1, new TestLogger));

    $this->beforeEach(function () use ($scheduler) {
        $scheduler->overrideOptions(['-v']);
    });

    /** We can set a job which will be processed */
    yield function () use ($scheduler) {
        $run = false;
        $scheduler->offsetSet('test', function () use (&$run) {
            $run = true;
        });
        ob_start();
        $scheduler->process();
        ob_end_clean();
        assert($run === true);
    };

    /** A job scheduled for the future won't be run */
    yield function () use ($scheduler) {
        $run = false;
        $scheduler->offsetSet('test',
            #[RunAt("2300-01-01")]
            function () use (&$run) {
                $run = true;
            }
        );
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert($run === false);
        assert(strpos($output, "Skipping test due to RunAt configuration.") !== false);
    };

    /** We can override duration - setting to 2 will cause process to run twice */
    yield function () use ($scheduler) {
        $run = 0;
        $scheduler = new Wrapper(new class (2, new TestLogger) extends Scheduler {
            public function __construct(int $duration, LoggerInterface $logger)
            {
                parent::__construct($duration, $logger);
                $this->sleeper = new class () extends Sleeper {
                    public function snooze(int $seconds) : void
                    {
                        $this->advanceInternalClock();
                    }
                };
            }
        });
        $scheduler->offsetSet('test', function () use (&$run) {
            $run++;
        });
        ob_start();
        $scheduler->process();
        ob_end_clean();
        assert($run === 2);
    };

    /** A job configured to only run on even minutes will be run once, even with duration=2 */
    yield function () use ($scheduler) {
        $run = 0;
        $scheduler = new Wrapper(new class (2, new TestLogger) extends Scheduler {
            public function __construct(int $duration, LoggerInterface $logger)
            {
                parent::__construct($duration, $logger);
                $this->sleeper = new class () extends Sleeper {
                    public function snooze(int $seconds) : void
                    {
                        $this->advanceInternalClock();
                    }
                };
            }
        });
        $scheduler->offsetSet('test',
            #[RunAt("Y-m-d H:[0-5][02468]")]
            function () use (&$run) {
                $run++;
            }
        );
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert($run === 1);
        assert(strpos($output, "Skipping test due to RunAt configuration.") !== false);
    };

    /** We can override options and get the currently set value */
    yield function () use ($scheduler) {
        $scheduler->overrideOptions(['--verbose']);
        $options = $scheduler->getOptions();
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
        $scheduler->offsetSet('test', Foo::class);
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert(strpos($output, 'I ran!') !== false);
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
        $scheduler->offsetSet('test', [new Bar, 'test']);
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert(strpos($output, 'I ran!') !== false);
    };

    /** A named function can be used as a task */
    yield function () use ($scheduler) {
        function test()
        {
            print "I ran!";
        }
        $scheduler->offsetSet('test', 'test');
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        assert(strpos($output, 'I ran!') !== false);
    };

    /** When a task is still running, the exclusive lock prevents it running twice */
    yield function () use ($scheduler) {
        $test = function () {
            sleep(1);
        };
        $scheduler->offsetSet('test', $test);
        file_put_contents(sys_get_temp_dir()."/croney.".md5(getcwd().':test').'.lock', '1');
        ob_start();
        $scheduler->process();
        $output = trim(ob_get_clean());
        unlink(sys_get_temp_dir()."/croney.".md5(getcwd().':test').'.lock');
        assert(strpos($output, "Couldn't aquire lock for test, skipping on this iteration.") !== false);
    };        

    /** The Sleeper can snooze and return current time */
    yield function () {
        $sleeper = new Wrapper(new Sleeper);
        $now = strtotime(date('Y-m-d H:i'));
        $sleeper->snooze(1);
        assert($sleeper->getDate() == date('Y-m-d H:i', strtotime('+1 minute', $now)));
        assert($sleeper->getDate('Y-m-01') == date('Y-m-01'));
    };

    /** The RunAt attribute correctly stores and returns dates */
    $object = new Wrapper(new Monolyth\Croney\RunAt('1999-12-31 23:59'));
    /** getDatetimeString yields $result === 'blarps' */
    yield function () use ($object) {
        $result = $object->getDatetimeString();
        assert($result === '1999-12-31 23:59');
    };
    
    /** getDateTime yields an instanceof DateTime */
    yield function () use ($object) {
        $result = $object->getDateTime();
        assert($result instanceof DateTime);
    };
};

