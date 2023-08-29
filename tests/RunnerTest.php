<?php

use Migrator\Runner;
use PHPUnit\Framework\TestCase;

class RunnerTest extends TestCase
{

    protected function getStepsFromFile($filename)
    {
        return \Symfony\Component\Yaml\Yaml::parse(
          file_get_contents($filename)
        );
    }

    /**
     * This test will simply check that the runner works generally
     *
     * @return void
     */
    function testBasicCase()
    {
        //just testing the test case callback
        $testRan = false;
        $cb = function ($args) use (&$testRan) {
            $testRan = true;
        };
        \Migrator\Step\Test::setCallback($cb);

        $steps = $this->getStepsFromFile(
          __DIR__ . "/assets/RunnerTestBasicCase.yaml"
        );
        $args = new \Migrator\RunnerArgs();
        $args->steps = $steps['steps'];
        $runner = new \Migrator\Runner($args);
        $runner->run();
        $this->assertTrue($testRan);
        \Migrator\Step\Test::clearCallbacks();
    }

    /**
     * This drives/tests the conditional system - basic conditional
     * Further tests will work on textual substitutions etc.
     *
     * @return void
     */
    function testConditionalRun()
    {
        //just testing the test case callback
        $conditionalStepRan = false;
        $cb = function ($args) use (&$conditionalStepRan) {
            if(!empty($args['testid']) && $args['testid'] == 1)
            {
                $conditionalStepRan = true;
            }
        };
        \Migrator\Step\Test::setCallback($cb);

        $steps = $this->getStepsFromFile(
          __DIR__ . "/assets/RunnerTestConditionalRun.yaml"
        );
        $args = new \Migrator\RunnerArgs();
        $args->steps = $steps['steps'];
        $runner = new \Migrator\Runner($args);
        $runner->run();
        $this->assertTrue($conditionalStepRan);
        \Migrator\Step\Test::clearCallbacks();
    }

    /**
     * This drives/tests the conditional system - basic conditional
     * Further tests will work on textual substitutions etc.
     *
     * @return void
     */
    function testConditionalDidntRun()
    {
        //just testing the test case callback
        $conditionalStepRan = false;
        $cb = function ($args) use (&$conditionalStepRan) {
            if(!empty($args['testid']) && $args['testid'] == 1)
            {
                $conditionalStepRan = true;
            }
        };
        \Migrator\Step\Test::setCallback($cb);

        $steps = $this->getStepsFromFile(
          __DIR__ . "/assets/RunnerTestConditionalDidntRun.yaml"
        );
        $args = new \Migrator\RunnerArgs();
        $args->steps = $steps['steps'];
        $runner = new \Migrator\Runner($args);
        $runner->run();
        $this->assertFalse($conditionalStepRan);
        \Migrator\Step\Test::clearCallbacks();
    }

    /**
     * This drives/tests the conditional system - basic conditional
     * Further tests will work on textual substitutions etc.
     *
     * @return void
     */
    function testConditionalWithTwigConditions()
    {
        //just testing the test case callback
        $conditionalStepShouldHaveRun = false;
        \Migrator\Step\DynamicEnvironmentTrait::setVariable('RUN_THIS', "true");
        \Migrator\Step\DynamicEnvironmentTrait::setVariable('DONT_RUN_THAT', "true");
        $conditionalStepShouldNotHaveRun = true;
        $cb = function ($args) use (&$conditionalStepShouldHaveRun, &$conditionalStepShouldNotHaveRun) {
            if(!empty($args['testid']) && $args['testid'] == 1)
            {
                $conditionalStepShouldHaveRun = true;
            }
            if(!empty($args['testid']) && $args['testid'] == 2)
            {
                $conditionalStepShouldNotHaveRun = false;
            }
        };
        \Migrator\Step\Test::setCallback($cb);

        $steps = $this->getStepsFromFile(
          __DIR__ . "/assets/RunnerTestConditionalTwig.yaml"
        );
        $args = new \Migrator\RunnerArgs();
        $args->steps = $steps['steps'];
        $runner = new \Migrator\Runner($args);
        $runner->run();
        $this->assertTrue($conditionalStepShouldHaveRun);
        $this->assertTrue($conditionalStepShouldNotHaveRun);

        \Migrator\Step\Test::clearCallbacks();
    }

}
