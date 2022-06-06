<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Deploy extends StepParent {

    public function run(array $args)
    {
        //check we have all the args we need

        $this->utilityBelt->deployEnvironment($this->args->project, $this->args->environment);
    }

}