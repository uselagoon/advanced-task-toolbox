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

    $dynamicEnv = new DynamicEnvironment();

    $dynamicEnv->fillDynamicEnvironmentFromEnv();

    $this->assertEquals($dynamicEnv->getVariable($envVarName), $envVarVal);

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
    $dynamicEnv = new DynamicEnvironment();
    $deployStep = new Deploy($lub, $runnerArgs, $dynamicEnv);

    $toSubString = "this should have {{ something }} here";
    $expected = "this should have words here";

    $dynamicEnv->setVariable("something", "words");

    $this->assertEquals($expected, $deployStep->doTextSubstitutions($toSubString));

    // here we also test defaults

    $toSubString = "this should have {{ doesntexist ?? 'default' }} here";
    $expected = "this should have default here";

    $this->assertEquals($expected, $deployStep->doTextSubstitutions($toSubString));

  }

  /**
   * Advanced tasks inject a "JSON_PAYLOAD" environment variable that contains
   * a base64 encoded array of arguments - here we test that this will be unwound
   * and loaded into the dynamic environment
   *
   * @return void
   * @throws \Exception
   */
  public function testJSONPAYLOADFILL() {

    // this looks like {"fullrun":"yes"}
    putenv("JSON_PAYLOAD=eyJmdWxscnVuIjoieWVzIn0=");

    $dynamicEnvironment = new DynamicEnvironment();
    $dynamicEnvironment->fillDynamicEnvironmentFromEnv();

    $this->assertEquals($dynamicEnvironment->getVariable("fullrun"), "yes");

  }

}
