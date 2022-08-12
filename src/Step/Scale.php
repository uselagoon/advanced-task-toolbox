<?php

namespace Migrator\Step;

use Migrator\UtilityBelt;

class Scale extends StepParent {

    public function run(array $args)
    {
        //check we have all the args we need
        if(empty($args['deployment'])) {
            throw new \Exception("Exec steps require a `deployment` argument");
        }

        $this->utilityBelt->scaleUpDeployment($args['deployment']);
    }

}