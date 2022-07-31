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


        //TODO: bail when deployment can't be found ...
        //scale this puppy up
        if ($deployment->getDesiredReplicasCount() == 0) {
            $this->log("Scaling up deployment for " . $deploymentName);
            $deployment->scale(1);
            $ready = false;
            for ($n = 0; $n <= 3; $n++) {
                sleep(10);
                $deployment->refresh();
                $readyReplicaCount = $deployment->getReadyReplicasCount();
                $this->log("ReadyReplica Count :%s\n", $readyReplicaCount);
                if ($readyReplicaCount > 0) {
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
        $this->log("Completed execution: " . var_export($results));
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