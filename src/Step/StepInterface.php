<?php

namespace Migrator\Step;

use Migrator\DynamicEnvironment;
use Migrator\LagoonUtilityBeltInterface;
use Migrator\RunnerArgs;

interface StepInterface
{

    public function __construct(DynamicEnvironment $env, LagoonUtilityBeltInterface $utilityBelt, RunnerArgs $args);

    public function run(array $args);

}
