<?php

namespace Migrator;

trait LoggerTrait
{

    public function log($message)
    {
        printf("%s :- %s \n", date("H:i:s"), $message);
    }

}