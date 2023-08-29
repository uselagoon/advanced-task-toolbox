<?php

namespace Migrator\Step;

use Migrator\DynamicEnvironment;
use Migrator\LagoonUtilityBelt;
use Migrator\RunnerArgs;
use PHPUnit\Framework\TestCase;

class DeployTest extends TestCase {

  public function testBasicRun() {
    $lub = $this->createStub(LagoonUtilityBelt::class);
    $lub->method("deployEnvironment")->willReturn("testBuildId");
    $runnerArgs = new RunnerArgs();

    $env = new DynamicEnvironment();
    $env->fillDynamicEnvironmentFromEnv();

    $deploy = new Deploy($env, $lub, $runnerArgs);
    $this->expectNotToPerformAssertions();
    $deploy->run([]);
  }

  public function testNoSubstitutionsInArgs() {
    $lub = $this->createMock(LagoonUtilityBelt::class);

    $buildVars = [];

    $args = [
      'project' => 'testproject',
      'environment' => 'testenvironment',
      'buildVariables' => $buildVars,
      'bulkName' => 'testBulkname',
    ];

    $lub->expects($this->once())
      ->method("deployEnvironment")
      ->with($args['project'], $args['environment'], null, $args['bulkName']);

    $runnerArgs = new RunnerArgs();
    $runnerArgs->project = $args['project'];
    $runnerArgs->environment = $args['environment'];

    $env = new DynamicEnvironment();
    $env->fillDynamicEnvironmentFromEnv();

    $deploy = new Deploy($env, $lub, $runnerArgs);

    $deploy->run($args);

  }

  public function testStandardTextualSubstitutionsInArgs() {
    $lub = $this->createMock(LagoonUtilityBelt::class);

    // Here we replace LAGOON_BACKUPS_DISABLED with whatever is
    // placed into the dynamic environment
    $buildVars = [["name" => 'LAGOON_BACKUPS_DISABLED', "value"=>'{{ LAGOON_BACKUPS_DISABLED }}']];

    $args = [
      'project' => 'testproject',
      'environment' => 'testenvironment',
      'buildVariables' => $buildVars,
      'bulkName' => 'testBulkname',
    ];

    // Once the textual subs for $buildVars is done, we expect the following...
    $buildWithSubs = [["name" => 'LAGOON_BACKUPS_DISABLED', "value"=>'false']];

    $lub->expects($this->once())
      ->method("deployEnvironment")
      ->with($args['project'], $args['environment'], $buildWithSubs, $args['bulkName']);

    $runnerArgs = new RunnerArgs();
    $runnerArgs->project = $args['project'];
    $runnerArgs->environment = $args['environment'];

    $env = new DynamicEnvironment();
    $env->fillDynamicEnvironmentFromEnv();

    $deploy = new Deploy($env, $lub, $runnerArgs);

    // Let's set up the basic text subs we want to do
    $env->setVariable('LAGOON_BACKUPS_DISABLED', "false");

    $deploy->run($args);

  }

}
