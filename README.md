# Croney
PHP-based CRON scheduler

Don't you hate having to juggle a gazillion cronjobs for each application? We
sure do! We _also_ hate having to adhere to a library-specific code format to
circumvent this problem (e.g. Symfony, Laravel... you know who you are).

You know what we'd like to do? We just want to register a bunch of callables
and have a central script figure it out. Hello Croney!

## Installation

### Composer (recommended)
```sh
composer require monomelodies/croney
```

### Manual
1. Download or clone the repository;
2. Add the namespace `Croney` for the path `path/to/croney/src` to your PSR-4
   autoloader.

## Setting up the executable
Croney needs to run periodically, so create a simple executable that we will add
as a cronjob:

```php
#!/usr/bin/php
<?php

// Let's assume this is in bin/cron.
// It's empty for now.
```

```sh
$ chmod a+x bin/cron
$ crontab -e
```

How often you let Croney run is up to you. The default assumption is every
minute since it is the smallest interval possible on Unix-like systems. We'll
see how to optimise this to e.g. every five minutes later on. For now, register
the cronjob with `* * * * *`, i.e. every minute.

## The `Scheduler` class
At Croney's core is an instance of the `Scheduler` class. This is what is used
to run tasks and it takes care (as the name implies) of scheduling them.

In your `bin/cron` file:

```
#!/usr/bin/php
<?php

use Croney\Scheduler;

$schedule = new Scheduler;
```

## Adding tasks
The `Scheduler` extends `ArrayObject`, so to add a task simply set it. The
simplest way is to add a callable:

```
#!/usr/bin/php
<?php

// ...
$schedule['some-task'] = function () {
    // ...perform the task...
};
```

This task gets run every minute (or whatever interval you set your cronjob to).
A task can be any callable, including class methods (even static ones).

You can also pass a class name, which will then get instantiated and
`__invoke`'d. If you need to pass construction arguments, do the instantiating
yourself.

When you've setup all your tasks, call `process` on the `Scheduler` to actually
run them:

```php
<?php

// ...
$schedule->process();
```

## Running tasks at specific intervals or times
To have more control over when exactly a task is run, add the
Monolyth\Croney\RunAt attribute to your invokable:

```
<?php

$scheduler['some-task'] =
    #[Monolyth\Croney\RunAt("Y-m-d H:m")]
    function () {
        // ...
    };
```

The parameter to `at` is a PHP date string which, when parsed using the run's
current time, should `preg_match` it. The above example runs the task every
minute (which is the default assuming your cronjob runs every minute). To run a
task every five minutes instead, you'd write something like this:

```php
<?php

$scheduler['some-task'] =
    #[Monolyth\Croney\RunAt("Y-m-d H:[0-5][05]")]
    function () {
        // ...
    };
```

Note that the `date` function works with placeholders, so if you need to regex
on e.g. a decimal (`\d`) you would need to double-escape it. See the PHP manual
page for `date` for a list of all valid placeholders.

> `preg_match` is called without checking string position, i.e. if you would
> pass only `'H'` as the date to match it would run on the hour, but also every
> minute (since 00-24 are all valid minutes and it would match `'i'` as well)
> _and_ also every day, month and (for all practical purposes since I'm not
> expecting this library to still be alive by the year 2399 ;)) all years. So be
> as specific as you need!

Note that the seconds part is irrelevant due to the granularity of cron and
should be omitted or your task will likely never run (since the date it is
compared to also doesn't include seconds).

Also note that if your task is an `__invoke`able object, the RunAt attribute
should be on the `__invoke` method, not the object itself.

## Running the script less often
We mentioned earlier how you can also choose to run the cronjob less often than
every minute, say every five minutes. If you only have tasks that run every five
minutes (or multiples of that), that's fine and no further configuration is
required. But what if you want to run your cronjob every five minutes, _but_
still be able to schedule tasks based on minutes?

> An example of this would be a cronjob that runs every five minutes, defining
> five tasks, each of which is run one minute after the previous task.

The first parameter when constructing the Scheduler object is actually its
duration (in minutes):

```php
<?php

$scheduler = new Scheduler(5); // Runs for five minutes
```

(As you'll have guessed, the default value here is `1`.)

When you call `process`, the tasks will actually be run 5 times (once every
minute) and executed when the time is there. E.g.:

```php
<?php

$scheduler = new Scheduler(5);

// First task, runs only on the first loop
$scheduler['first-task'] =
    #[RunAt("H:[0-5]0")]
    function () {
        // ...
    };
// Second task, runs only on the second loop
$scheduler['second-task']
    #[RunAt("")]
    = function () {
        $this->at('H:[0-5]1');
    };
// etc.
```

Croney calls PHP's `sleep` function in between loops.

> Croney tries to calculate the _actual_ number of seconds to sleep, so if the
> tasks from the first loop took, say, 3 seconds in total it sleeps for 57
> seconds before the next loop. Note however that this is _not_ exact and does
> _not_ guarantee that your task will run _exactly_ on the dot. If your task
> involves time-based operations make sure to "round down" the time to the
> expected value.

In theory, you could let your script run at midnight on January the first and
calculate everything from there. In the real world, this is obviously not
practical since any error whatsoever means you have to wait a whole year to see
if your fix solved the problem!

Typical values are every 5 or 10 minutes, maybe 30 or 60 on very busy servers.

## Long running tasks
Typically a task runs in (micro)seconds, but sometimes one of your tasks will be
"long running". If this is intentional (e.g. a periodic cleanup operation of
user-uploaded files) you would obviously `runAt` it at a safe interval, and you
should take care limit stuff in your task itself (e.g. "max 100 files per run").
Still, every so often you'll need to write a task that should run often, but
_might_ in extreme cases take longer than expected to do so.

A fictional example: a task that reads a mailbox (e.g. to push them into a
ticketing system). If that mailbox explodes for whatever reason (let's be
positive and imagine your application became _really_ popular overnight ;)) this
would pose a problem: the previous run might still be reading mails as the next
run starts, causing mails to be handled twice. Obviously not desirable.

Croney "locks" each task prior to running, and does not attempt to re-run as
long as it is locked. If a run fails due to locking, a warning is logged and the
task is retried periodically for as long as the cronjob runs. If the task
couldn't be run before the cronjob ends, an error is logged.

The locking is done based on an MD5 hash of the reflected callable, so any
changes between runs will invalidate any existing locks.

## Error handling
You can pass an instance of `Psr\Log\LoggerInterface` as an argument to the
`Scheduler` constructor. This will then be used to log any messages triggered by
tasks, in the way that you specified.

If no logger was defined, all messages go to `STDOUT` or `STDERR`.

Should your individual tasks also need logging, you'll need to supply them with
their own instance of a logger.

## Development
During development, you probably want to run tasks when testing (not just at a
specific time), and also probably just a specific task. Croney as of version 0.3
comes with two command line flags for this:

`--all|-a`
Use this flag to run all tasks, regardless of specified scheduling. *Do not do
this in production!*

`--job=jobname|-jjobname`
Run only the specified `jobname`. If the job is scheduled for particular times,
you'll likely want to use this in conjunction with the `--all` (or `-a`) flag.

You might also want to receive some more verbose feedback on what's going on. To
accomplish this, call your executable with the `--verbose` (or `-v`) flag.

If you need to test tasks in a longer running schedule, it's annoying to have
to wait minutes before your tasks complete. In that case, simply override the
scheduler's internal `sleeper` property. An example (that is actually used in
the unit tests for Croney itself):

```php
<?php

$scheduler = new class (2) extends Scheduler {
    public function __construct(int $duration)
    {
        parent::__construct($duration);
        $this->sleeper = new class () extends Sleeper {
            public function snooze(int $seconds) : void
            {
                // parent implementation: simply `sleep($seconds)`
                // next call is needed to realign the internal timer
                // so RunAt attributes keep working.
                $this->advanceInternalClock();
            }
        };
    }
};
```
