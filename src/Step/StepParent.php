<?php

namespace Migrator\Step;

use Migrator\UtilityBelt;

abstract class StepParent implements StepInterface
{

    protected $cluster;
    protected $namespace;
    protected $utilityBelt;
    protected $token;

    public function __construct($cluster, $namespace, $token)
    {
        $this->cluster = $cluster;
        $this->namespace = $namespace;
        $this->token = $token;
        $this->utilityBelt = new UtilityBelt($this->cluster, $this->namespace);
    }

    abstract public function run(array $args);

}