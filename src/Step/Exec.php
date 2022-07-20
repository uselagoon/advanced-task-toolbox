<?php

namespace Migrator\Step;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * This step will run a command either locally or via an exec
 * It allows for the following textual substitutions
 * %environment% = environment name
 * %project% = project name
 * %namespace% = k8s namespace
 */

class Exec extends StepParent {

    public function run(array $args)
    {
        $command = $this->doTextSubstitutions($args['command']);

        if(!empty($args['local']) && $args['local'] == "true" && !empty($command)) {

            $this->log("About to run command locally: " . $args['command']);

            $process = Process::fromShellCommandline($command);
            $process->run();

            if (!$process->isSuccessful() || $process->getExitCode() != 0) {
                throw new ProcessFailedException($process);
            }

            $this->log("Process output : " . $process->getOutput());
            return;
        }

        if(empty($args['deployment']) || empty($command)) {
            throw new \Exception("An Exec step requires `deployment` and `command` arguments");
        }
        $this->utilityBelt->execInPod($args['deployment'], $command);
    }

    protected function doTextSubstitutions($string)
    {
        $substitutions = [
          '%project%' => $this->args->project,
          '%environment%' => $this->args->environment,
          '%namespace%' => $this->args->namespace,
        ];

        foreach ($substitutions as $key => $value) {
            $string = str_replace($key, $value, $string);
        }
        return $string;
    }
}