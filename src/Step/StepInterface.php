<?php

namespace Migrator\Step;

interface StepInterface
{

    public function __construct($cluster, $namespace, $token);

    public function run(array $args);

}
