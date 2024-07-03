<?php

namespace Migrator\Step;

use Migrator\LagoonUtilityBeltInterface;
use Migrator\RunnerArgs;

interface StepInterface
{

    public function __construct(LagoonUtilityBeltInterface $utilityBelt, RunnerArgs $args, DynamicEnvironment $dynamicEnvironment);

    public function run(array $args);

}
