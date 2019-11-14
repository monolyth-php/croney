<?php

namespace Monolyth\Croney;

interface Timeable
{
    public function at(string $datestring) : void;
}

