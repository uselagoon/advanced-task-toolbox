<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Test extends StepParent {

    public static $callbacks = [];

    public static function setCallback(callable $cb)
    {
        self::$callbacks[] = $cb;
    }

    public static function clearCallbacks()
    {
        self::$callbacks = [];
    }

    public function runImplementation(array $args)
    {
        // check we have all the args we need
        // we just run through all the registered callbacks and call them with the args
        // This allows tests to attach their functions
        foreach (self::$callbacks as $callback) {
            if(is_callable($callback)) {
               $callback($args);
            }
        }
    }

}