<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;

class Deploy extends StepParent {

    public function runImplementation(array $args)
    {
        //check we have all the args we need
        $passIfTextExistsInLogs = key_exists("passIfTextExistsInLogs", $args) ? $args["passIfTextExistsInLogs"] : null;
        $variables = !empty($args['buildVariables']) ? $args['buildVariables'] : null;
        $bulkId = !empty($args['bulkId']) ? $args['bulkId'] : null;
        $this->utilityBelt->deployEnvironment($this->args->project, $this->args->environment, $variables, $bulkId, $passIfTextExistsInLogs);
    }

}