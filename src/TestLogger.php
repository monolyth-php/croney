<?php

namespace Monolyth\Croney;

use Stringable;

class TestLogger extends ErrorLogger
{
    #[Untestable]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        echo "$message\n";
    }
}

