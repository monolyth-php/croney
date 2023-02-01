<?php

namespace Monolyth\Croney;

use Attribute;
use DateTime;

#[Attribute]
class RunAt
{
    public function __construct(private string $datetime) {}

    public function getDatetimeString() : string
    {
        return $this->datetime;
    }

    public function getDateTime() : DateTime
    {
        return new DateTime($this->datetime);
    }
}

