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

    protected $endpoint;

    protected $lagoonName;

    public static function setUpLagoon_yml() {
        $TASK_API_HOST = getenv("TASK_API_HOST");
        $TASK_SSH_HOST = getenv("TASK_SSH_HOST");
        $TASK_SSH_PORT = getenv("TASK_SSH_PORT");
        if(!empty($TASK_API_HOST) && !empty($TASK_SSH_HOST) && !empty($TASK_SSH_PORT)) {
            exec("lagoon config add --create-config -g $TASK_API_HOST/graphql -H $TASK_SSH_HOST -P $TASK_SSH_PORT -l default --force && lagoon login --ssh-key /var/run/secrets/lagoon/ssh/ssh-privatekey");
        }
    }

    public function __construct(
      $k8sClient,
      $namespace,
      $lagoonSshKeyPath = null,
      $latestLagoonToken = null,
    ) {
        $this->lagoonSshKeyPath = $lagoonSshKeyPath;
        $endpoint = getenv("TASK_API_HOST");
        $this->endpoint = !empty($endpoint) ? $endpoint . "/graphql" : "https://api.amazeeio.cloud/graphql";
        $this->latestLagoonToken = $latestLagoonToken;

        $this->lagoonName = getEnv("LAGOON_NAME");

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

    public function startTaskInEnvironment($project, $environment, $taskName)
    {
        $query = '
query getTasksForEnv($projName: string) {
  projectByName(name: "demo-fsa") {
    environments {
      name
      advancedTasks {
        ... on AdvancedTaskDefinitionImage {
          id
          name
        }
      }
    }
  }
}';
        $client = $this->getLagoonPHPClient();
        try {
            $projectAdTasks = $client->json($query, ["projName" => $project]);
        } catch (\Exception $ex) {
            //TODO: do we want to implement any non-terrible error handling?
            throw $ex;
        }
        //        return (array)$projDeets->data->projectByName;
        //        foreach ($projectAdTasks->data->projectByName->environments)
        var_dump($projectAdTasks);
    }


    public function setDeployTargetForEnvironment(
      $project,
      $environmentName,
      $openshiftId
    ) {
        $e = $this->getEnvironmentDetails($project, $environmentName);
        //        if(!empty($e['openshift']) && !empty($e['openshift']->id)) {
        //            if($e['openshift']->id == $target) {
        //                throw new \Exception("Deploy target for {$project}:{$e} already set to {$target}");
        //            }
        //        }

        $p = $this->getProjectDetailsByName($project);

        $matchingDepTargetConfigs = [];
        //We want any rule we make to have the highest weight
        $highestWeight = 99;
        foreach ($p['deployTargetConfigs'] as $deployTargetConfig) {
            //if this holds, we have an exact match - we have to remove it
            print $deployTargetConfig->branches . "\n";
            if($deployTargetConfig->weight >= $highestWeight) {
                $highestWeight = $deployTargetConfig->weight + 1;
            }
            if ($deployTargetConfig->branches == $environmentName) {
                print "Found a deploy target config matching branch {$environmentName} - will delete\n";
                $this->deleteDeployTargetForProject(
                  $p['id'],
                  $deployTargetConfig->id
                );
            }
        }


        //This will now open up the possibility of us actually setting the
        $this->addDeployTargetForProject($p['id'], $environmentName, $openshiftId, $highestWeight);

        $this->updateEnvironmentDeployTarget($e['id'], $openshiftId);

        return true;
    }

    protected function updateEnvironmentDeployTarget(
      $environmentId,
      $deployTargetId
    ) {
        $query = '
  mutation updatedep($environmentId: Int!, $deployTargetId: Int!) {
  updateEnvironmentDeployTarget(environment:$environmentId, deployTarget:$deployTargetId) {
    id
    name
    openshift {
      id
      name
    }
  }
}
';
        $client = $this->getLagoonPHPClient();
        try {
            $result = $client->json(
              $query,
              [
                "environmentId" => $environmentId,
                "deployTargetId" => $deployTargetId,
              ]
            );
        } catch (\Exception $ex) {
            //TODO: do we want to implement any non-terrible error handling?
            throw $ex;
        }
        var_dump($result);
    }


    protected function addDeployTargetForProject(
      $projectId,
      $branches,
      $deployTargetId,
      $weight = 99
    ) {
        $query = '
mutation addDeployTargetConfig($projectId: Int!, $deployTargetId: Int!, $weight: Int!, $branches: String)  {
  addDeployTargetConfig(input: {
    project: $projectId
    deployTarget: $deployTargetId
    weight: $weight
    branches: $branches
  }) {
    id
  }
}
';
        $client = $this->getLagoonPHPClient();
        try {
            $result = $client->json(
              $query,
              [
                "projectId" => $projectId,
                "deployTargetId" => $deployTargetId,
                "weight" => $weight,
                "branches" => $branches,
              ]
            );
        } catch (\Exception $ex) {
            //TODO: do we want to implement any non-terrible error handling?
            throw $ex;
        }
        var_dump($result);
    }


    protected function deleteDeployTargetForProject(
      $projectId,
      $deployTargetConfigId
    ) {
        $query = '
        mutation deletedeptargconfig($projectId: Int!, $deployTargetConfigId: Int!) {
  deleteDeployTargetConfig(input: {
    id: $deployTargetConfigId
    project: $projectId
  })
}
';
        $client = $this->getLagoonPHPClient();
        try {
            $result = $client->json(
              $query,
              [
                "projectId" => $projectId,
                "deployTargetConfigId" => $deployTargetConfigId,
              ]
            );
        } catch (\Exception $ex) {
            //TODO: do we want to implement any non-terrible error handling?
            throw $ex;
        }
        var_dump($result);
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
    deployTargetConfigs {
      id
      deployTargetProjectPattern
      branches
      deployTarget {
        id
        name
      }
      weight
    }
  }
}';
        $client = $this->getLagoonPHPClient();
        try {
            $projDeets = $client->json($query, ["name" => $project]);
            var_dump($projDeets);
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
        var_dump($projectDetails);
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
        $token = $this->getLagoonToken();
        return new LagoonClient($this->endpoint, $token);
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
                    } elseif ($deployment->status == "failed") {
                        return false;
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

        if(!empty($this->lagoonName)) {
            $commandFull .= " -l {$this->lagoonName}";
        }

        $process = Process::fromShellCommandline($commandFull);
        $process->run();

        if (!$process->isSuccessful() || $process->getExitCode() != 0) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

}