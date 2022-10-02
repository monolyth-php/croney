<?php

namespace Monolyth\Croney;

use Psr\Log\LoggerInterface;
use Stringable;
use Gentry\Gentry\Untestable;

class ErrorLogger implements LoggerInterface
{
    #[Untestable]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    #[Untestable]
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    #[Untestable]
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    #[Untestable]
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    #[Untestable]
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    #[Untestable]
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(STDERR, $message, $context);
    }

    #[Untestable]
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(STDOUT, $message, $context);
    }

    #[Untestable]
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(STDOUT, $message, $context);
    }

    #[Untestable]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        fwrite($level, "$message\n");
    }
}

