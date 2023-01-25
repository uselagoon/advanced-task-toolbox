<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Deploy extends StepParent
{

    public function runImplementation(array $args)
    {
        //check we have all the args we need
        $passIfTextExistsInLogs = key_exists(
          "passIfTextExistsInLogs",
          $args
        ) ? $args["passIfTextExistsInLogs"] : null;
        $variables = !empty($args['buildVariables']) ? $args['buildVariables'] : null;
        $bulkName = !empty($args['bulkName']) ? $args['bulkName'] : null;
        $skipDeploymentWait = !empty($args['skipDeploymentWait']) ? $args['skipDeploymentWait'] : false;
        $registerBuildIdAs = !empty($args['registerBuildIdAs']) ? $args['registerBuildIdAs'] : false;
        $buildId = $this->utilityBelt->deployEnvironment(
          $this->args->project,
          $this->args->environment,
          $variables,
          $bulkName
        );
        if (!$skipDeploymentWait) {
            $this->utilityBelt->processWaitForDeploymentToComplete(
              $this->args->project,
              $this->args->environment,
              $buildId,
              $passIfTextExistsInLogs
            );
        }
        if($registerBuildIdAs !== false) {
            self::setVariable($registerBuildIdAs, $buildId);
        }
    }

}