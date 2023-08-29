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

        // Do substitutions on Target
        $target = self::doTextSubstitutions($args['target']);
        if(!is_numeric($target)) {
          throw new \Exception(sprintf("The target specified is not numeric: %s", $target));
        }
        $targetInt = intval($target);

        $this->log("Setting deploy target to {$args['target']}");
        $this->utilityBelt->setDeployTargetForEnvironment($this->args->project, $this->args->environment, $targetInt);
    }

}