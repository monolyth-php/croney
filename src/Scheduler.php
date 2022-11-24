<?php

namespace Monolyth\Croney;

use InvalidArgumentException;
use Exception;
use ArrayObject;
use Psr\Log\LoggerInterface;
use GetOpt\GetOpt;
use Monolyth\Cliff\Command;
use Closure;
use ReflectionFunction;
use ReflectionObject;
use Monolyth\Disclosure\Factory;

class Scheduler extends ArrayObject
{
    protected Sleeper $sleeper;

    private array $jobs = [];

    private static ?GetOpt $getopt;

    private static ?array $options = null;

    /**
     * Constructor. Optionally pass a duration (in minutes) and a Logger
     * implementing Psr\Log\LoggerInterface (e.g. Monolog\Logger).
     *
     * @param Psr\Log\LoggerInterface|null $logger
     * @param int $duration
     * @return void
     */
    public function __construct(private int $duration = 1, private ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new ErrorLogger;
        $this->sleeper = new Sleeper;
    }

    /**
     * Add a job to the schedule. Note the type hints do not reflect the actual
     * requirements, to satisfy compatibility with ArrayObject::offsetSet.
     *
     * @param string $name
     * @param callable $job The job.
     */
    public function offsetSet(mixed $name, mixed $job) : void
    {
        if (is_string($job) && class_exists($job)) {
            $job = class_exists(Factory::class) ? Factory::build($job) : new $job;
        }
        if (!is_callable($job)) {
            $this->logger->critical("Job $name is not callable.");
            throw new InvalidArgumentException('Each job must be callable');
        }
        if (is_numeric($name)) {
            $this->logger->warning("Job $name has a numeric index; it is better to use named indexes.");
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
        $specific = null;
        $options = $this->getOptions();
        $specific = $options->getOption('j');
        $start = time();
        $tmp = sys_get_temp_dir();
        $verbose = $options->getOption('v');
        $always = $options->getOption('a');
        array_walk($this->jobs, function ($job, $idx) use ($tmp, $specific, $verbose, $always) : void {
            if (isset($specific) && $specific !== $idx) {
                return;
            }
            if (!$always && !$this->shouldRun($job)) {
                if ($verbose) {
                    $this->logger->info("Skipping $idx due to RunAt configuration.");
                }
                return;
            }
            if ($verbose) {
                $this->logger->info("Starting $idx...");
            }
            $lockfile = "$tmp/croney.".md5(getcwd().':'.$idx).'.lock';
            if (file_exists($lockfile)) {
                if ($verbose) {
                    $this->logger->warning("Couldn't aquire lock for $idx, skipping on this iteration.");
                }
                return;
            }
            file_put_contents($lockfile, '1');
            try {
                call_user_func($job);
            } catch (Exception $e) {
                $this->logger->critial(sprintf(
                    "%s in file %s on line %d",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
            }
            unlink($lockfile);
            if ($verbose) {
                $this->logger->info("$idx: done.");
            }
        });
        if (--$this->duration > 0) {
            $this->sleeper->snooze(max(60 - (time() - $start), 0));
            $this->process();
        }
    }

    /**
     * Get all CLI options for the scheduler.
     *
     * @return GetOpt\GetOpt
     */
    public static function getOptions() : GetOpt
    {
        if (!isset(self::$getopt)) {
            self::$getopt = new GetOpt([
                ['j', 'job', GetOpt::REQUIRED_ARGUMENT],
                ['v', 'verbose', GetOpt::NO_ARGUMENT],
                ['a', 'all', GetOpt::NO_ARGUMENT],
            ]);
            self::$getopt->process(self::$options);
        }
        return self::$getopt;
    }

    /**
     * Override options. This is useful when calling the scheduler from a
     * non-cron context, e.g. during tests or when your own cron script accepts
     * different parameters.
     *
     * @param array $options Array of option overrides.
     * @return void
     */
    public static function overrideOptions(array $options) : void
    {
        self::$options = $options;
        self::$getopt = null;
    }

    private function shouldRun(callable $job) : bool
    {
        if ($job instanceof Closure || (is_string($job) && function_exists($job))) {
            $reflection = new ReflectionFunction($job);
        } else {
            if (is_array($job)) {
                list($job, $method) = $job;
            } else {
                $method = '__invoke';
            }
            $reflection = (new ReflectionObject($job))->getMethod($method);
        }
        $attributes = $reflection->getAttributes(RunAt::class);
        if (!$attributes) {
            return true;
        }
        $runAt = $attributes[0]->newInstance()->getDatetimeString();
        $date = $this->sleeper->getDate($runAt);
        return preg_match("@$date$@", $this->sleeper->getDate());
    }
}

