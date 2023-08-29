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
            var_dump($args);
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

}