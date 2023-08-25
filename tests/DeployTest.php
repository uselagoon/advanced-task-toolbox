<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;
use Migrator\RunnerArgs;
use PHPUnit\Framework\TestCase;

class DeployTest extends TestCase {

  public function testBasicRun() {
    $lub = $this->createStub(LagoonUtilityBelt::class);
    $lub->method("deployEnvironment")->willReturn("testBuildId");
    $runnerArgs = new RunnerArgs();
    $deploy = new Deploy($lub, $runnerArgs);
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

    $deploy = new Deploy($lub, $runnerArgs);

    $deploy->run($args);

  }

  public function testStandardTextualSubstitutionsInArgs() {
    $lub = $this->createMock(LagoonUtilityBelt::class);

    // Here we replace LAGOON_BACKUPS_DISABLED with whatever is
    // placed into the dynamic environment
    $buildVars = ['LAGOON_BACKUPS_DISABLED', '{{ LAGOON_BACKUPS_DISABLED }}'];

    $args = [
      'project' => 'testproject',
      'environment' => 'testenvironment',
      'buildVariables' => $buildVars,
      'bulkName' => 'testBulkname',
    ];

    // Once the textual subs for $buildVars is done, we expect the following...
    $buildWithSubs = ['LAGOON_BACKUPS_DISABLED', 'false'];

    $lub->expects($this->once())
      ->method("deployEnvironment")
      ->with($args['project'], $args['environment'], $buildWithSubs, $args['bulkName']);

    $runnerArgs = new RunnerArgs();
    $runnerArgs->project = $args['project'];
    $runnerArgs->environment = $args['environment'];

    $deploy = new Deploy($lub, $runnerArgs);

    // Let's set up the basic text subs we want to do
    Deploy::setVariable('LAGOON_BACKUPS_DISABLED', "false");

    $deploy->run($args);

  }

}
