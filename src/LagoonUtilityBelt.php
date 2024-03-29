<?php

namespace Migrator;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Lagoon\LagoonClient;

class LagoonUtilityBelt extends UtilityBelt implements LagoonUtilityBeltInterface {

  static $DEPLOY_WAIT_TIMEOUT = 60;

  static $DEPLOY_WAIT_ATTEMPTS = 120; //iterations of $DEPLOY_WAIT_TIMEOUT - i.e. two hours.

  protected $lagoonSshKeyPath;

  protected $latestLagoonToken;

  protected $endpoint;

  protected $lagoonName;

  public static function setUpLagoon_yml() {
    $TASK_API_HOST = getenv("TASK_API_HOST");
    $TASK_SSH_HOST = getenv("TASK_SSH_HOST");
    $TASK_SSH_PORT = getenv("TASK_SSH_PORT");
    if (!empty($TASK_API_HOST) && !empty($TASK_SSH_HOST) && !empty($TASK_SSH_PORT)) {
      exec(
        "lagoon config add --create-config -g $TASK_API_HOST/graphql -H $TASK_SSH_HOST -P $TASK_SSH_PORT -l default --force && lagoon login --ssh-key /var/run/secrets/lagoon/ssh/ssh-privatekey"
      );
    }
  }

  public function __construct(
    $k8sClient,
    $namespace,
    $lagoonSshKeyPath = NULL,
    $latestLagoonToken = NULL,
    ) {
    $this->lagoonSshKeyPath = $lagoonSshKeyPath;
    $endpoint = getenv("TASK_API_HOST");
    $this->endpoint = !empty($endpoint) ? $endpoint . "/graphql" : "https://api.amazeeio.cloud/graphql";
    $this->latestLagoonToken = $latestLagoonToken;

    $this->lagoonName = getEnv("LAGOON_NAME");

    parent::__construct($k8sClient, $namespace);
  }

  // Here follows arbitrary deployment functions
  public function deployEnvironment(
    $project,
    $environment,
    $variables,
    $bulkName = NULL
  ) {
    $this->log(sprintf("About to deploy %s:%s", $project, $environment));
    $query = '
mutation deployit($environmentName: String!, $projectName: String!, $bulkName: String, $bulkId: String, $buildVariables: [EnvKeyValueInput]) {
deployEnvironmentLatest(input: {
    environment: {
      name: $environmentName
      project: {
        name: $projectName
      }
    },returnData: true,
    buildVariables: $buildVariables,
    bulkName: $bulkName,
    bulkId: $bulkId
  })
}';
    $client = $this->getLagoonPHPClient();


    try {
      $args = ["projectName" => $project, "environmentName" => $environment];
      if (!empty($variables) && count($variables) > 0) {
        $args['buildVariables'] = $variables;
      }
      if (!empty($bulkName)) {
        $args['bulkName'] = $bulkName;
        $args['bulkId'] = $bulkName;
      }
      $projectAdTasks = $client->json($query, $args);

      var_dump($projectAdTasks);

      $id = $projectAdTasks->data->deployEnvironmentLatest;
      //TODO: check if $id is a fail
    } catch (\Exception $ex) {
      //TODO: do we want to implement any non-terrible error handling?
      throw $ex;
    }
    return $id;
  }

  /**
   * @param $project
   * @param $environment
   * @param $id
   * @param $passFailedDeploymentIfTextExists
   *
   * @return bool|void
   * @throws \Exception
   */
  public function processWaitForDeploymentToComplete(
    $project,
    $environment,
    $id,
    $passFailedDeploymentIfTextExists
  ) {
    $completionState = $this->waitForDeploymentToComplete(
      $project,
      $environment,
      $id
    );
    switch ($completionState) {
      case ("complete"):
        return TRUE;
      case ("failed"):
        $this->log("Got completionstate: " . $completionState);

        if (!empty($passFailedDeploymentIfTextExists)) {
          $this->log(
            "Waiting 2 minutes for log shipping and then checking if text '{$passFailedDeploymentIfTextExists}' exists in log for build {$id}"
          );
          sleep(120);
          //here we need to grab the build's logs ...
          $buildLog = $this->getBuildLogByBuildName(
            $project,
            $environment,
            $id
          );
          if (!empty($buildLog)) {
            if (str_contains($buildLog, $passFailedDeploymentIfTextExists)) {
              $this->log(
                "Found matching text in deploy log - treating deployment as success"
              );
              return TRUE;
            }
            else {
              throw new \Exception(
                "Deployment failed - no string '{$passFailedDeploymentIfTextExists}' not found in build log"
              );
            }
          }
          else {
            throw new \Exception("Deployment failed - no buildlog found");
          }
        }
        else {
          throw new \Exception("Deployment failed - exiting");
        }
      case ("cancelled"):
        throw new \Exception("Deployment was cancelled by user");
    }
  }

  public function getBuildLogByBuildName($project, $environment, $buildId) {
    $query = '
query getBuildlog($projName: String!, $buildName: String!) {
 projectByName(name: $projName) {
  environments {
    name
		deployments(name: $buildName) {
			buildLog
    }
  }
}
}';

    $client = $this->getLagoonPHPClient();
    try {
      $buildLogs = $client->json(
        $query,
        ["projName" => $project, "buildName" => $buildId]
      );

      foreach ($buildLogs->data->projectByName->environments as $e) {
        if ($e->name == $environment) {
          foreach ($e->deployments as $deployment) {
            if (!empty($deployment->buildLog)) {
              return $deployment->buildLog;
            }
          }
        }
      }
      return FALSE;
    } catch (\Exception $ex) {
      //TODO: do we want to implement any non-terrible error handling?
      throw $ex;
    }
  }


  public function startTaskInEnvironment($project, $environment, $taskName) {
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
  }

  public function setDeployTargetForEnvironment(
    $project,
    $environmentName,
    $openshiftId
  ) {
    $e = $this->getEnvironmentDetails($project, $environmentName);
    if (!empty($e['openshift']) && !empty($e['openshift']->id)) {
      if ($e['openshift']->id == $openshiftId) {
        throw new \Exception(
          "Deploy target for $project:$e already set to $openshiftId"
        );
      }
    }

    $p = $this->getProjectDetailsByName($project);

    $matchingDepTargetConfigs = [];
    //We want any rule we make to have the highest weight
    $highestWeight = 99;
    foreach ($p['deployTargetConfigs'] as $deployTargetConfig) {
      //if this holds, we have an exact match - we have to remove it
      //            print $deployTargetConfig->branches . "\n";
      if ($deployTargetConfig->weight >= $highestWeight) {
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
    $this->addDeployTargetForProject(
      $p['id'],
      $environmentName,
      $openshiftId,
      $highestWeight
    );

    $this->updateEnvironmentDeployTarget($e['id'], $openshiftId);

    return TRUE;
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
    $this->log(
      "Successfully updated deploy target to {$deployTargetId}"
    );
    $this->logVerbose(
      "Updating setting of deploy target result: " . print_r($result, TRUE)
    );
  }


  protected function addDeployTargetForProject(
    $projectId,
    $branches,
    $deployTargetId,
    $weight = 99
  ) {
    $query = '
mutation addDeployTargetConfig($projectId: Int!, $deployTargetId: Int!, $weight: Int!, $branches: String!)  {
  addDeployTargetConfig(input: {
    project: $projectId
    deployTarget: $deployTargetId
    weight: $weight
    branches: $branches
    pullrequests: "false"
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
    $this->log("Successfully updated deploy target config");
    $this->logVerbose(
      "Updating adding of deploy target config's result: " . print_r(
        $result,
        TRUE
      )
    );
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
    $this->log("Successfully deleted deploy target config");
    $this->logVerbose(
      "Updating setting of deleting deploy target config result: " . print_r(
        $result,
        TRUE
      )
    );
  }


  public function refreshLagoonToken() {
    $this->latestLagoonToken = trim($this->runLagoonCommand("get token"));
  }


  public function getProjectDetailsByName($project) {
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
    } catch (\Exception $ex) {
      //TODO: do we want to implement any non-terrible error handling?
      throw $ex;
    }
    return (array) $projDeets->data->projectByName;
  }

  public function getEnvironmentDetails($project, $environment) {
    //get project id first
    $projectDetails = $this->getProjectDetailsByName($project);
    $query = 'query environmentByNameVar($projectId: Int!, $envName: String!) {
  environmentByName(project: $projectId, name: $envName) {
    id
    name
    openshiftProjectPattern
    environmentType
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
      return (array) $envDeets->data->environmentByName;
    } catch (\Exception $ex) {
      //TODO: do we want to implement any non-terrible error handling?
      throw $ex;
    }
  }

  //Just used to test ...

  public function whoIsMe() {
    $query = "query whome {
  me {
    id
  }
}";
    $client = $this->getLagoonPHPClient();
    $response = $client->json($query);
    return $response;
  }

  protected function getLagoonPHPClient() {
    $token = $this->getLagoonToken();
    return new LagoonClient($this->endpoint, $token);
  }

  public function getLagoonToken() {
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
      $resp = $this->runLagoonCommand($commandText, TRUE);
      $obj = json_decode(trim($resp));
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception(json_last_error_msg());
      }
      $found = FALSE;
      foreach ($obj->data as $deployment) {
        if ($deployment->name == $deploymentId) {
          $this->logVerbose(
            sprintf(
              "Found %s with status %s",
              $deploymentId,
              $deployment->status
            )
          );

          if (in_array(
            $deployment->status,
            ["complete", "failed", "cancelled"] //End states
          )) {
            return $deployment->status;
          }

          $this->logVerbose(
            "Deployment {$deploymentId} currently in state: " . $deployment->status
          );
          $found = TRUE;
        }
      }
      if (!$found) {
        throw new \Exception(
          "Could not find build {$deploymentId} in list of deployments for {$project}:{$environment}"
        );
      }
      $this->log(
        "Waiting for " . self::$DEPLOY_WAIT_TIMEOUT . " to see status of deployment"
      );
      sleep(self::$DEPLOY_WAIT_TIMEOUT);
    }
    throw new \Exception(
      "Timeout waiting for build {$deploymentId} to complete"
    );
  }

  protected function runLagoonCommand($command, $quiet = FALSE) {
    $commandFull = "lagoon " . $command;
    if (!empty($this->lagoonSshKeyPath)) {
      $commandFull .= " --ssh-key {$this->lagoonSshKeyPath}";
    }

    if (!empty($this->lagoonName)) {
      $commandFull .= " -l {$this->lagoonName}";
    }

    if (!$quiet) {
      $this->log("Running lagoon command: $commandFull");
    }
    else {
      $this->logVerbose("Running lagoon command: $commandFull");
    }
    $process = Process::fromShellCommandline($commandFull);
    $process->run();

    if (!$process->isSuccessful() || $process->getExitCode() != 0) {
      throw new ProcessFailedException($process);
    }

    return $process->getOutput();
  }

}