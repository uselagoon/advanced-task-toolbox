<?php

namespace Migrator;

trait LoggerTrait
{
    public function log($message)
    {
        printf("%s :- %s \n", date("H:i:s"), $message);
    }

    public function logVerbose($message)
    {
        if(strtolower(getenv("LAGOON_LOG_VERBOSE")) == "true" ) {
            printf("%s :- %s \n", date("H:i:s"), $message);
        }
    }

}