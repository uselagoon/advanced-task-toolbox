<?php

namespace Migrator;
use League\CLImate\CLImate;


trait LoggerTrait
{
    public function log($message)
    {
        $climate = new CLImate;
        $climate->out(sprintf("%s :- %s ", date("H:i:s"), $message));
    }

    public function logVerbose($message)
    {
        $climate = new CLImate;
        if(strtolower(getenv("LAGOON_LOG_VERBOSE")) == "true" ) {
            $climate->out(sprintf("%s :- %s ", date("H:i:s"), $message));
        }
    }

}