<?php

namespace Migrator;

class Runner
{
    protected $steps;
    protected $cluster;
    protected $namespace;
    protected $token;

    public function __construct($migrationSteps, $cluster, $namespace, $token = null)
    {
        $this->steps = $migrationSteps;
        $this->cluster = $cluster;
        $this->namespace = $namespace;
    }

    public function run()
    {
        foreach ($this->steps as $step) {
            if(empty($step['type'])) {
                throw new \Exception("Step does not exist");
            }
            //here we autoload up the steps
            $classname = "Migrator\\Step\\" . ucfirst(strtolower($step['type']));
            if(!class_exists($classname)) {
                throw new \Exception("Class '{$classname} does not exist");
            }
            $stepObj = new $classname($this->cluster, $this->namespace, $this->token);
            $stepObj->run($step);
        }
    }
}