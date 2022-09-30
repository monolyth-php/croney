<?php

namespace Monolyth\Croney;

use InvalidArgumentException;
use Exception;
use ArrayObject;
use Psr\Log\LoggerInterface;
use GetOpt\GetOpt;
use Monolyth\Cliff\Command;
use Closure;

class Scheduler extends ArrayObject
{
    private array $jobs = [];

    private static GetOpt $getopt;

    private static ?array $options = null;

    /**
     * Constructor. Optionally pass a Monolog\Logger and a duration (in
     * minutes).
     *
     * @param Psr\Log\LoggerInterface|null $logger
     * @param int $duration
     * @return void
     */
    public function __construct(private ?LoggerInterface $logger = null, private int $duration = 1)
    {
        $this->now = strtotime(date('Y-m-d H:i:00'));
        $this->logger = $logger ?? new ErrorLogger;
    }

    /**
     * Get the logger.
     *
     * @return Psr\Log\LoggerInterface
     */
    public function getLogger(string $property) : LoggerInterface
    {
        return $property == 'logger' ? $this->logger : null;
    }

    /**
     * Add a job to the schedule.
     *
     * @param string $name
     * @param callable $job The job.
     */
    public function offsetSet($name, $job)
    {
        if (is_string($job) && class_exists($job)) {
            $job = new $job;
        }
        if (!is_callable($job)) {
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
                echo "Skipping $idx due to RunAt configuration.\n";
                return;
            }
            if ($verbose) {
                echo "Starting $idx...";
            }
            $fp = fopen("$tmp/".md5($idx).'.lock', 'w+');
            flock($fp, LOCK_EX);

            try {
                if ($job instanceof Closure) {
                    $job->call($this);
                } else {
                    $job();
                }
            } catch (Exception $e) {
                $this->logger->critial(sprintf(
                    "%s in file %s on line %d",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
            }
            flock($fp, LOCK_UN);
            fclose($fp);
            if ($verbose) {
                echo " [done]\n";
            }
        });
        if (--$this->duration > 0) {
            $wait = max(60 - (time() - $start), 0);
            sleep($wait);
            $this->now = strtotime('+1 minute', date('Y-m-d H:i', $this->now));
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
     * @param array|null $options Array of option overrides. Pass null to reset.
     * @return void
     */
    public static function overrideOptions(array $options = null) : void
    {
        self::$options = $options;
        self::$getopt = null;
    }

    private function shouldRun(object $job) : bool
    {
        if ($job instanceof Closure) {
            $reflection = new ReflectionFunction($job);
        } else {
            $reflection = (new ReflectionObject($job))->getMethod('__invoke');
        }
        $attributes = $reflection->getAttributes(RunAt::class);
        if ($attributes) {
            $runat = $attributes[0]->getDatetimeString();
        }
        $date = date($runat, $this->now);
        return preg_match("@$date$@", date('Y-m-d H:i', $this->now));
    }
}

