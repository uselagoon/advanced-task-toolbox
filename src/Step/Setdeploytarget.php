<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Setdeploytarget extends StepParent {

    public function runImplementation(array $args)
    {
        //check we have all the args we need
        if(empty($args['target'])) {
            throw new \Exception("Step type setdeploytarget requires an argument 'target'");
        }
        $this->log("Setting deploy target to {$args['target']}");
        $this->utilityBelt->setDeployTargetForEnvironment($this->args->project, $this->args->environment, $args['target']);
    }

}