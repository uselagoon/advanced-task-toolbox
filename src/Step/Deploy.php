<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Deploy extends StepParent {

    public function run(array $args)
    {
        //check we have all the args we need
        var_dump($args);
        $passIfTextExistsInLogs = key_exists("passIfTextExistsInLogs", $args) ? $args["passIfTextExistsInLogs"] : null;
        $this->utilityBelt->deployEnvironment($this->args->project, $this->args->environment, $passIfTextExistsInLogs);
    }

}