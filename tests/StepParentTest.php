<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;
use Migrator\RunnerArgs;
use PHPUnit\Framework\TestCase;

class StepParentTest extends TestCase {

  public function testFillDynamicEnvironmentFromEnv() {

    $envVarName = uniqid("TESTVAR-");
    $envVarVal = uniqid();
    putenv(sprintf("%s=%s", $envVarName, $envVarVal));

    StepParent::fillDynamicEnvironmentFromEnv();

    $this->assertEquals(StepParent::getVariable($envVarName), $envVarVal);

  }

  /**
   * This will let us test the cases where the variables being substituted
   * are of the form `%name:default%` where there is no variable `name` and
   * the environment returns the value `default`
   *
   * @return void
   */
  public function testSubstitutions() {

    $lub = $this->createStub(LagoonUtilityBelt::class);
    $lub->method("deployEnvironment")->willReturn("testBuildId");
    $runnerArgs = new RunnerArgs();
    $deployStep = new Deploy($lub, $runnerArgs);

    $toSubString = "this should have {{ something }} here";
    $expected = "this should have words here";

    StepParent::setVariable("something", "words");

    $this->assertEquals($expected, $deployStep->doTextSubstitutions($toSubString));

    // here we also test defaults

    $toSubString = "this should have {{ doesntexist ?? 'default' }} here";
    $expected = "this should have default here";

    $this->assertEquals($expected, $deployStep->doTextSubstitutions($toSubString));

  }


}
