<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;
use Migrator\LagoonUtilityBeltInterface;
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


    /**
     * This test looks at derived classes/child classes and ensures that they have access to the step-parent's dynamic
     * environment functionality - which they should, even the inheretence hierarchy
     *
     * @return void
     * @throws \Exception
     */
  public function testDynamicEnvironmentInChildren() {
      $dynamicEnv = new DynamicEnvironment();
      $args = new RunnerArgs();

      $dynamicutilityBelt = new class() implements LagoonUtilityBeltInterface
      {
          public function deployEnvironment($project, $environment, $variables, $bulkName = NULL){}
          public function processWaitForDeploymentToComplete($project, $environment, $id, $passFailedDeploymentIfTextExists){}
          public function getBuildLogByBuildName($project, $environment, $buildId){}
          public function startTaskInEnvironment($project, $environment, $taskName){}
          public function setDeployTargetForEnvironment($project, $environmentName, $openshiftId){}
          public function refreshLagoonToken(){}
          public function getProjectDetailsByName($project){}
          public function getEnvironmentDetails($project, $environment){}
          public function whoIsMe(){}
          public function getLagoonToken(){}
          public function waitForDeploymentToComplete($project, $environment, $deploymentId){}
      };

      $dynamicclass = new class($dynamicutilityBelt, $args, $dynamicEnv) extends StepParent
      {

          protected function runImplementation(array $args)
          {
              $this->dynamicEnvironment->setVariable("thecalliscomingfrom", "insidethehouse");
          }

          public function callMeToTest() {
              $this->run([]);
          }
      };

      $dynamicclass->callMeToTest();

      $this->assertEquals("insidethehouse", $dynamicEnv->getVariable("thecalliscomingfrom"));
  }

}
