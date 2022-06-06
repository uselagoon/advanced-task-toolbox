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
    )
    {
        $this->steps = $args->steps;
        $this->cluster = $args->cluster;
        $this->namespace = $args->namespace;
        $this->token = $args->token;
        $this->args = $args;
    }

    public function __get($name)
    {
        if(property_exists($this->args, $name) && !empty($this->args->{$name})) {
            return $this->args->{$name};
        }

        throw new \Exception("Unable to find attribute {$name} on args object");
    }

    public function run()
    {
        foreach ($this->steps as $step) {
            if (empty($step['type'])) {
                throw new \Exception("Step does not exist");
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

}