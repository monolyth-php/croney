<?php

namespace Monolyth\Croney;

use Psr\Log\LoggerInterface;
use Stringable;

class ErrorLogger implements LoggerInterface
{
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(STDOUT, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(STDOUT, $message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        fwrite($level, "$message\n");
    }
}

