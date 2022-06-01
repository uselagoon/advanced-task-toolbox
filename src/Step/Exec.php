<?php

namespace Migrator\Step;

class Exec extends StepParent {
    public function run(array $args)
    {
        if(empty($args['deployment']) || empty($args['command'])) {
            throw new \Exception("An Exec step requires `deployment` and `command` arguments");
        }
        $this->utilityBelt->execInPod($args['deployment'], $args['command']);
    }
}