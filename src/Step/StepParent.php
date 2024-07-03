<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBelt;
use Migrator\LagoonUtilityBeltInterface;
use Migrator\LoggerTrait;
use Migrator\RunnerArgs;
use Migrator\UtilityBelt;
use function WyriHaximus\Twig\render;

abstract class StepParent implements StepInterface
{
    use LoggerTrait;
    protected $cluster;
    protected $namespace;
    protected $utilityBelt;
    protected $token;
    protected $args;
    protected $commandName;
    protected $dynamicEnvironment;

    public function __construct(LagoonUtilityBeltInterface $utilityBelt, RunnerArgs $args, DynamicEnvironment $environment)
    {
        $this->cluster = $args->cluster;
        $this->namespace = $args->namespace;
        $this->token = $args->token;
        $this->args = $args;
        $this->utilityBelt = $utilityBelt;
        $this->dynamicEnvironment = $environment;
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
    $environmentClean = str_replace("/","-", $this->args->environment);
    $extraSubs = [
      'project' => $this->args->project,
      'environment' => $this->args->environment,
      'environmentClean' => $environmentClean,
      'namespace' => $this->args->namespace,
    ];

      return $this->dynamicEnvironment->renderText($string, $extraSubs);
  }

    // We use dynamic dispatch to allow us to do some logging and
    // cleanup from the parent, and then have the child provide a (protected)
    // implementation for our public facing "run()" to call internally
    abstract protected function runImplementation(array $args);

}