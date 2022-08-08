<?php

namespace Migrator\Step;

/**
 * This class is going to go mostly undocumented, but it's useful for migrating
 * since having multiple backup schedules across clusters leads to confusion
 */

class Deletebackupschedule extends StepParent {
    public function run(array $args)
    {
        $command = "delete schedule k8up-lagoon-backup-schedule";
        $this->utilityBelt->runKubectl($command, $this->token);
    }
}