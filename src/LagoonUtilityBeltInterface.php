<?php

namespace Migrator;

interface LagoonUtilityBeltInterface {

  public function deployEnvironment($project, $environment, $variables, $bulkName = NULL);

  /**
   * @param $project
   * @param $environment
   * @param $id
   * @param $passFailedDeploymentIfTextExists
   *
   * @return bool|void
   * @throws \Exception
   */
  public function processWaitForDeploymentToComplete($project, $environment, $id, $passFailedDeploymentIfTextExists);

  public function getBuildLogByBuildName($project, $environment, $buildId);

  public function startTaskInEnvironment($project, $environment, $taskName);

  public function setDeployTargetForEnvironment($project, $environmentName, $openshiftId);

  public function refreshLagoonToken();

  public function getProjectDetailsByName($project);

  public function getEnvironmentDetails($project, $environment);

  public function whoIsMe();

  public function getLagoonToken();

  public function waitForDeploymentToComplete($project, $environment, $deploymentId);

}