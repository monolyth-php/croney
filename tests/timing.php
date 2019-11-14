<?php

use Gentry\Gentry\Wrapper;

/** Testsuite for Monolyth\Croney\Timing */
return function () : Generator {
    $object = Wrapper::createObject(Monolyth\Croney\Timing::class);
    /** at yields $result === void */
    yield function () use ($object) {
        $e = null;
        try {
            $object->at(date('Y-m-d H:i', strtotime('+1 hour')));
        } catch (Monolyth\Croney\NotDueException $e) {
        }
        assert($e instanceof Monolyth\Croney\NotDueException);
    };

};

