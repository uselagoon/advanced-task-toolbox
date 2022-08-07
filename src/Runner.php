<?php

namespace Migrator;

class Runner
{

    protected $steps;

    protected $cluster;

    protected $namespace;

    protected $token;

    protected $args;

    public function __construct(RunnerArgs $args
    ) {
        $this->steps = $args->steps;
        $this->cluster = $args->cluster;
        $this->namespace = $args->namespace;
        $this->token = $args->token;
        $this->args = $args;
    }

    public function __get($name)
    {
        if (property_exists(
            $this->args,
            $name
          ) && !empty($this->args->{$name})) {
            return $this->args->{$name};
        }

        throw new \Exception("Unable to find attribute {$name} on args object");
    }

    public function run()
    {
        foreach ($this->steps as $step) {
            //determine if this is a step or an assertion
            if (!empty($step['assertTrue']) || !empty($step['assertFalse'])) {
                $this->runAssertion($step);
            } else {
                $this->runStep($step);
            }
        }
    }

    /**
     * @param $step
     *
     * @return void
     * @throws \Exception
     */
    protected function runAssertion($step)
    {
        if (!empty($step['assertTrue']) && !empty($step['assertFalse'])) {
            throw new \Exception(
              "An assertion cannot be true and false at the same time - found both assertTrue and assertFalse"
            );
        }

        $expectedAssertionResult = true;
        $assertion = null;
        if (!empty($step['assertTrue'])) {
            $assertion = sprintf("Assert%s", strtolower($step['assertTrue']));
            $expectedAssertionResult = true;
        } else {
            $assertion = sprintf("Assert%s", strtolower($step['assertFalse']));
            $expectedAssertionResult = false;
        }


        //here we autoload up the steps
        $classname = "Migrator\\Assert\\" . $assertion;

        if (!class_exists($classname)) {
            throw new \Exception("Class '{$classname} does not exist - could not load assertion");
        }

        $stepObj = new $classname($this->args);
        $assertionResult = $stepObj->assert($step);

        if ($assertionResult != $expectedAssertionResult) {
            $expectedAssertionResultString = $expectedAssertionResult ? 'TRUE' : 'FALSE';
            $assertionResultString = $assertionResult ? 'TRUE' : 'FALSE';
            throw new \Exception("Assertion '{$step['name']}' failed - expected {$expectedAssertionResultString} got $assertionResultString");
        }
    }

    /**
     * @param $step
     *
     * @return void
     * @throws \Exception
     */
    protected function runStep($step)
    {
        if (empty($step['type'])) {
            throw new \Exception(
              "Step to run is not defined - ensure there is a 'step' field"
            );
        }
        //here we autoload up the steps
        $classname = "Migrator\\Step\\" . ucfirst(
            strtolower($step['type'])
          );
        if (!class_exists($classname)) {
            throw new \Exception("Class '{$classname} does not exist");
        }

        $stepObj = new $classname($this->args);
        $stepObj->run($step);
    }

}