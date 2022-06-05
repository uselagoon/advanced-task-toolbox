<?php

namespace Migrator;

use \RenokiCo\PhpK8s\Kinds\K8sDeployment;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class LagoonUtilityBelt extends UtilityBelt
{

    static $DEPLOY_WAIT_TIMEOUT = 60;
    static $DEPLOY_WAIT_ATTEMPTS = 15; //15 iterations of $DEPLOY_WAIT_TIMEOUT

    protected $lagoonSshKeyPath;

    public function __construct($k8sClient, $namespace, $lagoonSshKeyPath = null)
    {
        $this->lagoonSshKeyPath = $lagoonSshKeyPath;
        parent::__construct($k8sClient, $namespace);
    }

    // Here follows arbitrary deployment functions
    public function deployEnvironment($project, $environment) {
        $resp = $this->runLagoonCommand("deploy latest -p {$project} -e {$environment} --returnData --force");
        $id = trim($resp);
        $this->waitForDeploymentToComplete($project, $environment, $id);

    }

    public function getLagoonToken() {
        $token = $this->runLagoonCommand("get token");
        return trim($token);
    }

    public function waitForDeploymentToComplete($project, $environment, $deploymentId) {
        $commandText = "list deployments -p {$project} -e {$environment} --output-json";

        for($i = 0; $i < self::$DEPLOY_WAIT_ATTEMPTS; $i++) {
            $resp = $this->runLagoonCommand($commandText);
            $obj = json_decode(trim($resp));
            if(json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(json_last_error_msg());
            }
            $found = false;
            foreach ($obj->data as $deployment) {
                var_dump("`{$deployment->name}` looking for  `{$deploymentId}`");
                if($deployment->name == $deploymentId) {
                    printf("Found %s with status %s", $deploymentId, $deployment->status);
                    if($deployment->status == "complete") {
                        return true;
                    }
                    print("Deployment {$deploymentId} currently in state: " . $deployment->status);
                    $found = true;
                }

            }
            if(!$found) {
                throw new \Exception("Could not find build {$deploymentId} in list of deployments for {$project}:{$environment}");
            }
            print "Waiting for " . self::$DEPLOY_WAIT_TIMEOUT . " to see status of deployment";
            sleep(self::$DEPLOY_WAIT_TIMEOUT);

        }
        throw new \Exception("Timeout waiting for build {$deploymentId} to complete");
    }

    protected function runLagoonCommand($command) {

        $commandFull = "lagoon " . $command;
        if(!empty($this->lagoonSshKeyPath)) {
            $commandFull .= "--ssh-key {$this->lagoonSshKeyPath}";
        }

        $process = Process::fromShellCommandline($commandFull);
        $process->run();

        if (!$process->isSuccessful() || $process->getExitCode() != 0) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

}