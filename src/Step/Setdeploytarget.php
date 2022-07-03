<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Setdeploytarget extends StepParent {

    public function run(array $args)
    {
        //check we have all the args we need
        $this->utilityBelt->setDeployTargetForEnvironment($this->args->project, $this->args->environment, $args['target']);
    }

}