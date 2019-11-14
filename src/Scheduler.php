<?php

namespace Monolyth\Croney;

use InvalidArgumentException;
use Exception;
use ArrayObject;
use Monolog\Logger;

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
        foreach ($_SERVER['argv'] as $arg) {
            if (preg_match('@--job=(.*?)$@', $arg, $match)) {
                $specific = $match[1];
            }
        }
        $start = time();
        $tmp = sys_get_temp_dir();
        array_walk($this->jobs, function ($job, $idx) use ($tmp, $specific, $_SERVER['argv']) {
            if (isset($specific) && $specific !== $idx) {
                return;
            }
            if (in_array('--verbose', $_SERVER['argv']) || in_array('-v', $_SERVER['argv'])) {
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
            if (in_array('--verbose', $_SERVER['argv']) || in_array('-v', $_SERVER['argv'])) {
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
}

