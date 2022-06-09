<?php

namespace Migrator;

use \RenokiCo\PhpK8s\Kinds\K8sDeployment;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Lagoon\LagoonClient;

class LagoonUtilityBelt extends UtilityBelt
{

    static $DEPLOY_WAIT_TIMEOUT = 60;

    static $DEPLOY_WAIT_ATTEMPTS = 60; //60 iterations of $DEPLOY_WAIT_TIMEOUT - i.e. an hour.

    protected $lagoonSshKeyPath;

    protected $latestLagoonToken;

    public function __construct(
      $k8sClient,
      $namespace,
      $lagoonSshKeyPath = null
    ) {
        $this->lagoonSshKeyPath = $lagoonSshKeyPath;
        parent::__construct($k8sClient, $namespace);
    }

    // Here follows arbitrary deployment functions
    public function deployEnvironment($project, $environment)
    {
        $resp = $this->runLagoonCommand(
          "deploy latest -p {$project} -e {$environment} --returnData --force"
        );
        $id = trim($resp);
        $this->waitForDeploymentToComplete($project, $environment, $id);
    }

    public function setDeployTargetForEnvironment($project, $environmentName, $target)
    {
//        $e = $this->getEnvironmentDetails($project, $environmentName);
//        if(!empty($e['openshift']) && !empty($e['openshift']->id)) {
//            if($e['openshift']->id == $target) {
//                throw new \Exception("Deploy target for {$project}:{$e} already set to {$target}");
//            }
//        }
//
//        //step one
//
//
//        $client = $this->getLagoonPHPClient();
//        try {
//            $envDeetsNew = $client->json($query, ["envid" => $e['id'], "targetID" => $target]);
//        } catch (\Exception $ex) {
//            //TODO: do we want to implement any non-terrible error handling?
//            throw $ex;
//        }

    }


    public function refreshLagoonToken()
    {
        $this->latestLagoonToken = trim($this->runLagoonCommand("get token"));
    }


    public function getProjectDetailsByName($project)
    {
        $query = '
query projectByNameVar($name: String!) {
  projectByName(name: $name) {
    id
    name
  }
}';
        $client = $this->getLagoonPHPClient();
        try {
            $projDeets = $client->json($query, ["name" => $project]);
        } catch (\Exception $ex) {
            //TODO: do we want to implement any non-terrible error handling?
            throw $ex;
        }
        return (array)$projDeets->data->projectByName;
    }


    public function getEnvironmentDetails($project, $environment)
    {
        //get project id first
        $projectDetails = $this->getProjectDetailsByName($project);
        $query = 'query environmentByNameVar($projectId: Int!, $envName: String!) {
  environmentByName(project: $projectId, name: $envName) {
    id
    name
    openshiftProjectPattern
    openshift {
      id
      friendlyName
    }
  }
}';

        $client = $this->getLagoonPHPClient();
        try {
            $envDeets = $client->json(
              $query,
              ["projectId" => $projectDetails['id'], "envName" => $environment]
            );
            return (array)$envDeets->data->environmentByName;
        } catch (\Exception $ex) {
            //TODO: do we want to implement any non-terrible error handling?
            throw $ex;
        }
    }

    //Just used to test ...
    public function whoIsMe()
    {
        $query = "query whome {
  me {
    id
  }
}";
        $client = $this->getLagoonPHPClient();
        $response = $client->json($query);
        return $response;
    }

    protected function getLagoonPHPClient()
    {
        $endpoint = "https://api.amazeeio.cloud/graphql";
        $token = $this->getLagoonToken();
        return new LagoonClient($endpoint, $token);
    }

    public function getLagoonToken()
    {
        if (empty($this->latestLagoonToken)) {
            $this->refreshLagoonToken();
        }
        return $this->latestLagoonToken;
    }

    public function waitForDeploymentToComplete(
      $project,
      $environment,
      $deploymentId
    ) {
        $commandText = "list deployments -p {$project} -e {$environment} --output-json";

        for ($i = 0; $i < self::$DEPLOY_WAIT_ATTEMPTS; $i++) {
            $resp = $this->runLagoonCommand($commandText);
            $obj = json_decode(trim($resp));
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(json_last_error_msg());
            }
            $found = false;
            foreach ($obj->data as $deployment) {
                var_dump(
                  "`{$deployment->name}` looking for  `{$deploymentId}`"
                );
                if ($deployment->name == $deploymentId) {
                    printf(
                      "Found %s with status %s",
                      $deploymentId,
                      $deployment->status
                    );
                    if ($deployment->status == "complete") {
                        return true;
                    }
                    print("Deployment {$deploymentId} currently in state: " . $deployment->status);
                    $found = true;
                }
            }
            if (!$found) {
                throw new \Exception(
                  "Could not find build {$deploymentId} in list of deployments for {$project}:{$environment}"
                );
            }
            print "Waiting for " . self::$DEPLOY_WAIT_TIMEOUT . " to see status of deployment";
            sleep(self::$DEPLOY_WAIT_TIMEOUT);
        }
        throw new \Exception(
          "Timeout waiting for build {$deploymentId} to complete"
        );
    }

    protected function runLagoonCommand($command)
    {
        $commandFull = "lagoon " . $command;
        if (!empty($this->lagoonSshKeyPath)) {
            $commandFull .= " --ssh-key {$this->lagoonSshKeyPath}";
        }

        $process = Process::fromShellCommandline($commandFull);
        $process->run();

        if (!$process->isSuccessful() || $process->getExitCode() != 0) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

}