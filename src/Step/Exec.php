<?php

namespace Migrator\Step;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Exec extends StepParent {
    public function run(array $args)
    {
        if(!empty($args['local']) && $args['local'] == "true" && !empty($args['command'])) {

            $process = Process::fromShellCommandline($args['command']);
            $process->run();

            if (!$process->isSuccessful() || $process->getExitCode() != 0) {
                throw new ProcessFailedException($process);
            }

            var_dump($process->getOutput());
            return;
        }

        if(empty($args['deployment']) || empty($args['command'])) {
            throw new \Exception("An Exec step requires `deployment` and `command` arguments");
        }
        $this->utilityBelt->execInPod($args['deployment'], $args['command']);
    }
}