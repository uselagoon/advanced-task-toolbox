<?php

namespace Migrator\Step;

use Migrator\UtilityBelt;

class Waitforfile extends StepParent {

    static $SLEEP = 1;

    public function runImplementation(array $args)
    {
        //check we have all the args we need
        if(empty($args['filename'])) {
            throw new \Exception("WaitForFile requires a filename");
        }

        for($timeout = 0; $timeout <= 100; $timeout++) {
            if(file_exists($args['filename'])) {
                printf("WaitForFile caught signal - exiting cleanly\n");
                return;
            }
            sleep(self::$SLEEP);
        }
        throw new \Exception("Timeout expired without file existing");
    }
}