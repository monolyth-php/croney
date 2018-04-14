<?php

namespace Monolyth\Croney;

class ErrorLogger
{
    /**
     * @param string $name
     * @param array $args
     */
    public function __call(string $name, array $args)
    {
        if (preg_match('@^add[A-Z]@', $name)
            && isset($args[0])
            && is_string($args[0])
        ) {
            fwrite(STDERR, $args[0]."\n");
        }
    }
}

