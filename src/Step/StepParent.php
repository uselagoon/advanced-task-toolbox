<?php

namespace Migrator\Step;

use _PHPStan_7dd5f1b1b\Nette\Neon\Exception;
use Migrator\LagoonUtilityBelt;
use Migrator\LagoonUtilityBeltInterface;
use Migrator\LoggerTrait;
use Migrator\RunnerArgs;
use Migrator\UtilityBelt;
use function WyriHaximus\Twig\render;

abstract class StepParent implements StepInterface
{
    use LoggerTrait, DynamicEnvironmentTrait;
    protected $cluster;
    protected $namespace;
    protected $utilityBelt;
    protected $token;
    protected $args;
    protected $commandName;

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
    $extraSubs = [
      'project' => $this->args->project,
      'environment' => $this->args->environment,
      'namespace' => $this->args->namespace,
    ];

      return self::renderText($string, $extraSubs);
  }

    // We use dynamic dispatch to allow us to do some logging and
    // cleanup from the parent, and then have the child provide a (protected)
    // implementation for our public facing "run()" to call internally
    abstract protected function runImplementation(array $args);

}