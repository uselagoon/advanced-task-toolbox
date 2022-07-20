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

    public function __construct(RunnerArgs $args)
    {
        $this->cluster = $args->cluster;
        $this->namespace = $args->namespace;
        $this->token = $args->token;
        $this->args = $args;
        $this->utilityBelt = new LagoonUtilityBelt($this->cluster, $this->namespace, $args->sshKey);
    }

    abstract public function run(array $args);

}