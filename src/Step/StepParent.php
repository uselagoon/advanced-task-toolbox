<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;
use Migrator\LagoonUtilityBeltInterface;
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

    public function __construct(LagoonUtilityBeltInterface $utilityBelt, RunnerArgs $args)
    {
        $this->cluster = $args->cluster;
        $this->namespace = $args->namespace;
        $this->token = $args->token;
        $this->args = $args;
        $this->utilityBelt = $utilityBelt;
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

  /**
   * This will take a string and do any textual substitutions from the current
   * dynamic environment
   *
   * @param $string
   *
   * @return array|string|string[]
   */
  public function doTextSubstitutions($string)
  {
    $substitutions = [
      '%project%' => $this->args->project,
      '%environment%' => $this->args->environment,
      '%namespace%' => $this->args->namespace,
    ];

    //Here we reach into the dynamic environment to grab any other arbitrarily defined vars
    foreach (self::getAllVariables() as $key => $value) {
      $substitutions["%{$key}%"] =  $value;
    }


    foreach ($substitutions as $key => $value) {
      $string = str_replace($key, $value, $string);
    }
    return $string;
  }

    // We use dynamic dispatch to allow us to do some logging and
    // cleanup from the parent, and then have the child provide a (protected)
    // implementation for our public facing "run()" to call internally
    abstract protected function runImplementation(array $args);

}