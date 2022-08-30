<?php

namespace Migrator\Step;

class Copyto extends StepParent {
    public function runImplementation(array $args)
    {
        if(empty($args['deployment']) || empty($args['source']) || empty($args['destination'])) {
            throw new \Exception("An Exec step requires `deployment`, `source`, and `destination` arguments");
        }

        $podName = $this->utilityBelt->getPodFromDeployment($args['deployment']);

        $command = "cp {$args['source']} {$podName->getName()}:{$args['destination']}";
        $this->utilityBelt->runKubectl($command, $this->token);
    }
}