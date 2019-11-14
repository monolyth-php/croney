<?php

namespace Monolyth\Croney;

use InvalidArgumentException;
use Exception;
use ArrayObject;
use Monolog\Logger;
use GetOpt\GetOpt;

class Scheduler extends ArrayObject implements Timeable, Durable
{
    use Timing;
    use Duration;

    /** @var array */
    private $jobs = [];
    /** @var Monolog\Logger */
    private $logger;

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
        $options = self::getOptions();
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
                $job->call($this);
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
     * @param array|null $options Optional options override.
     * @return GetOpt\GetOpt
     */
    public static function getOptions(array $options = null) : GetOpt
    {
        static $getopt;
        if (!isset($getopt)) {
            $getopt = new GetOpt([
                ['j', 'job', GetOpt::REQUIRED_ARGUMENT],
                ['v', 'verbose', GetOpt::NO_ARGUMENT],
                ['a', 'all', GetOpt::NO_ARGUMENT],
            ]);
            $getopt->process($options);
        }
        return $getopt;
    }
}

