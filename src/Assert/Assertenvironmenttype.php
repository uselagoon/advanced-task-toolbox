<?php

namespace Migrator\Assert;

class Assertenvironmenttype extends AssertParent
{

    const environmentTypes = ['production', 'development'];

    public function assert(array $args): bool
    {
        if(empty($args['environmentType']) || !in_array(strtolower($args['environmentType']), self::environmentTypes)) {
            throw new \Exception("Assertion EnvironmentType required a field 'environmentType' with either 'production' or 'development' as a value");
        }
        //Get current environment information
        $envdeets = $this->utilityBelt->getEnvironmentDetails($this->args->project, $this->args->environment);
        return strtolower($args['environmentType']) == strtolower($envdeets['environmentType']);
    }

}