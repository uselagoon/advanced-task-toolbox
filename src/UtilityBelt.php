<?php

namespace Migrator;

use \RenokiCo\PhpK8s\Kinds\K8sDeployment;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class UtilityBelt
{

    use LoggerTrait;

    /** @var \RenokiCo\PhpK8s\KubernetesCluster */
    protected $client;

    protected $namespace;

    /** @var LoggerTrait */
    private $loggerTrait;

    public function __construct($k8sClient, $namespace)
    {
        $this->client = $k8sClient;
        $this->namespace = $namespace;
    }

    public function scaleUpDeployment($deploymentName)
    {
        $deployment = $this->getDeployment($deploymentName);

        $SLEEP_WAITING_FOR_DEPLOYMENT = 10; //sleep for 10 seconds a go
        $NUMBER_SLEEPS_TO_TAKE_WAITING_FOR_DEPLOYMENT = 30; //We'll wait 5 minutes if we're sleeping for 10 seconds

        //TODO: bail when deployment can't be found ...
        //scale this puppy up
        if ($deployment->getDesiredReplicasCount() == 0) {
            $this->log("Scaling up deployment for " . $deploymentName);
            $deployment->scale(1);
            $ready = false;
            for ($n = 0; $n <= $NUMBER_SLEEPS_TO_TAKE_WAITING_FOR_DEPLOYMENT; $n++) {
                sleep($SLEEP_WAITING_FOR_DEPLOYMENT);
                $deployment->refresh();
                $readyReplicaCount = $deployment->getReadyReplicasCount();
                $this->log(sprintf("ReadyReplica Count :%s\n", $readyReplicaCount));
                if ($readyReplicaCount == 1) {
                    $ready = true;
                    break;
                }
            }
            if (!$ready) {
                throw new \Exception("COULD NOT SCALE DEPLOYMENT $deploymentName");
            }
        } else {
            $this->log(
              "{$this->namespace}:{$deploymentName} already has running pods - no need to scale"
            );
        }
    }

    public function getPodFromDeployment($deploymentName)
    {
        $deployment = $this->getDeployment($deploymentName);
        K8sDeployment::selectPods(function (K8sDeployment $dep) {
            return [
              'lagoon.sh/service' => "{$dep->getName()}",
            ];
        });

        /** @var \RenokiCo\PhpK8s\Kinds\K8sPod $pod */
        foreach ($deployment->getPods() as $pod) {
            $metadata = $pod->getAttribute("metadata");
            if (empty($metadata['deletionTimestamp'])) {
                return $pod;
            }
        }
        throw new \Exception("Could not find a suitable pod");
    }


    public function getLagoonEnv()
    {
        $configMap = $this->client->configmap()
          ->whereNamespace($this->namespace)
          ->whereName("lagoon-env")
          ->get()
          ->getData();
        return $configMap;
    }


    public function execInPod($deploymentName, $command)
    {
        $pod = $this->getPodFromDeployment($deploymentName);
        $this->log("About to to execute `{$command}` in pod " . $pod->getName());
        $results = $pod->exec(['/bin/sh', '-c', $command]);

        // iterate through array and check for channel "error"

        $prettyOutput = "";
        if(is_array($results)) {
            foreach ($results as $result) {
                if(key_exists('channel', $result) && $result['channel'] == 'error') {
                    $this->log("Failed execution: " . var_export($results));
                    throw new \Exception("Exec failed with the following error: " . $result['output']);
                }
                if(key_exists('output', $result)) {
                    $prettyOutput .= $result['output'] . "\n";
                }
            }
        }

        $this->log("Completed execution: \n" . $prettyOutput);
    }

    /**
     * @param $deploymentName
     *
     * @return \RenokiCo\PhpK8s\Kinds\K8sDeployment
     * @throws \RenokiCo\PhpK8s\Exceptions\KubernetesAPIException
     */
    protected function getDeployment($deploymentName
    ): \RenokiCo\PhpK8s\Kinds\K8sDeployment {
        //Check if deployment exists ...
        /** @var \RenokiCo\PhpK8s\Kinds\K8sDeployment $deployment */
        $deployment = $this->client->deployment()
          ->whereNamespace($this->namespace)
          ->whereName($deploymentName)
          ->get();
        return $deployment;
    }


    /**
     * @param $command kubectl command to be run - without ns or kubectl
     * @param $token
     *
     * @return void
     */
    public function runKubectl($command, $token=null) {

        $command = "kubectl -n {$this->namespace} " . $command;

        $this->log("About to run the following command: " . $command);

        if(!empty($token)) {
            $command .= " --token={$token}";
        }


        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful() || $process->getExitCode() != 0) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();

    }


}