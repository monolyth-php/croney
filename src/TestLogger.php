<?php

namespace Monolyth\Croney;

use Stringable;
use Gentry\Gentry\Untestable;

class TestLogger extends ErrorLogger
{
    #[Untestable]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        echo "$message\n";
    }
}

