<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;
use Migrator\LoggerTrait;
use Migrator\RunnerArgs;
use Migrator\UtilityBelt;

abstract class StepParent implements StepInterface
{
    use LoggerTrait;
    protected $cluster;
    protected $namespace;
    protected $utilityBelt;
    protected $token;
    protected $args;
    protected $commandName;

    static $dynamicEnvironment = [];

    public static function setVariable($name, $value) {
            self::$dynamicEnvironment[$name] = $value;
    }

    public static function getVariable($name) {
        if(!key_exists($name, self::$dynamicEnvironment)) {
            throw new \Exception("Unable to find variable {$name} in dynamic environment - have you previously set it?");
        }
        return self::$dynamicEnvironment[$name];
    }

    public static function getAllVariables() {
        return self::$dynamicEnvironment;
    }

    public function __construct(RunnerArgs $args)
    {
        $this->cluster = $args->cluster;
        $this->namespace = $args->namespace;
        $this->token = $args->token;
        $this->args = $args;
        $this->utilityBelt = new LagoonUtilityBelt($this->cluster, $this->namespace, $args->sshKey);
    }

    /**
     * This opens a section header that's used by the ui to organize
     *
     * @param $sectionName
     *
     * @return void
     */
    public function printOpenLogSection($sectionName) {
        print("##############################################\n" .
          "BEGIN $sectionName\n" .
          "##############################################\n");
    }

    public function run(array $args) {
        $this->commandName = !empty($args["name"]) ? $args["name"] : get_class($this);
        $this->printOpenLogSection($this->commandName);
        $this->runImplementation($args);
    }

    // We use dynamic dispatch to allow us to do some logging and
    // cleanup from the parent, and then have the child provide a (protected)
    // implementation for our public facing "run()" to call internally
    abstract protected function runImplementation(array $args);

}