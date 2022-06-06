<?php

namespace Migrator\Step;

use Migrator\RunnerArgs;

interface StepInterface
{

    public function __construct(RunnerArgs $args);

    public function run(array $args);

}
