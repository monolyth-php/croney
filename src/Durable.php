<?php

namespace Monolyth\Croney;

interface Durable
{
    public function setDuration(int $minutes) : void;
}

