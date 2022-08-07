<?php

namespace Migrator\Assert;

class Assertcurrentopenshift extends AssertParent
{

    public function assert(array $args): bool
    {
        if(empty($args['target'])) {
            throw new \Exception("assertcurrentopenshift requires a 'target' field");
        }
        //Get current environment information
        $envdeets = $this->utilityBelt->getEnvironmentDetails($this->args->project, $this->args->environment);
        return $args['target'] == $envdeets['openshift']->id;
    }

}