<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Waitfordeploy extends StepParent
{

    public function runImplementation(array $args)
    {
        //check we have all the args we need
        $passIfTextExistsInLogs = key_exists(
          "passIfTextExistsInLogs",
          $args
        ) ? $args["passIfTextExistsInLogs"] : null;
        $buildId = !empty($args['buildId']) ? $this->dynamicEnvironment->getVariable($args['buildId']) : null;
        if (empty($buildId)) {
            throw new \Exception(
              "Cannot run step waitfordeploy without setting a 'buildId'"
            );
        }

        $this->utilityBelt->processWaitForDeploymentToComplete(
          $this->args->project,
          $this->args->environment,
          $buildId,
          $passIfTextExistsInLogs
        );
    }

}