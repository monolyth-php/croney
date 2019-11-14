<?php

use Gentry\Gentry\Wrapper;

/** Tests for duration */
return function () : Generator {
    $object = Wrapper::createObject(Monolyth\Croney\Duration::class);
    /** Using setDuration we can make a job run longer than a minute (i.e., execute twice) */
    yield function () use ($object) {
        $object->setDuration(2);
        $reflection = new ReflectionProperty($object, 'minutes');
        $reflection->setAccessible(true);
        assert($reflection->getValue($object) === 2);
    };

};

