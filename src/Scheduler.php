<?php

namespace Monolyth\Croney;

use InvalidArgumentException;
use Exception;
use ArrayObject;
use Monolog\Logger;
use GetOpt\GetOpt;
use Monolyth\Cliff\Command;
use Closure;

class Scheduler extends ArrayObject implements Timeable, Durable
{
    use Timing;
    use Duration;

    /** @var array */
    private $jobs = [];
    /** @var Monolog\Logger */
    private $logger;
    /** @var GetOpt\GetOpt */
    private static $getopt;
    /** @var array|null */
    private static $options = null;

    /**
     * Constructor. Optionally pass a Monolog\Logger.
     *
     * @param Monolog\Logger|null $logger
     * @return void
     */
    public function __construct(Logger $logger = null)
    {
        $this->now = strtotime(date('Y-m-d H:i:00'));
        $this->logger = $logger ?? new ErrorLogger;
    }

    /**
     * Getter. Only accepts `"logger"` as a property.
     *
     * @param string $property
     * @return Monolog\Logger|null
     */
    public function __get(string $property)
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
        array_walk($this->jobs, function ($job, $idx) use ($tmp, $specific, $verbose) {
            if (isset($specific) && $specific !== $idx) {
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
            } catch (NotDueException $e) {
            } catch (Exception $e) {
                $this->logger->addCritial(sprintf(
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
        if (--$this->minutes > 0) {
            $wait = max(60 - (time() - $start), 0);
            if (!getenv('TOAST')) {
                sleep($wait);
            }
            $this->now += 60;
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
}

