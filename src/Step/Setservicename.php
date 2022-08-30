<?php

namespace Migrator\Step;

class Setservicename extends StepParent {
    public function runImplementation(array $args)
    {
        if(empty($args['servicename'])) {
            throw new \Exception("Setservicename steps require a service name");
        }

        //first check if there is already a lagoon service set

        //if not, we can proceed to set it ...
        //pod name is the host name, so we grab that ...
        $podName = gethostname();
        if($podName === FALSE) {
            throw new \Exception("Unable to get pod's hostname for setting service details");
        }
        $command = "label pods {$podName} lagoon.sh/service={$args['servicename']}";
        $this->utilityBelt->runKubectl($command, $this->token);
    }
}