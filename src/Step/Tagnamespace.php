<?php

namespace Migrator\Step;

/**
 * This step type is really a stand in for namespace labelling functionality
 * for the moment it will create a secret with the label name
 */
class Tagnamespace extends StepParent
{

    public function run(array $args)
    {
                if (empty($args['label'])) {
                    throw new \Exception("Tagnamespace step requires a 'label'");
                }
                $label = $args['label'];
                $command = "create secret  generic advanced-task-toolbox-{$label}";
                $this->log("Going to create secret 'advanced-task-toolbox-{$label}' in '{$this->namespace}' ");
                $this->utilityBelt->runKubectl($command, $this->token);
                $this->log("Going to label secret for searching with 'advanced-task-toolbox-{$label}'");
                $command = "label secret advanced-task-toolbox-{$label} advanced-task-toolbox-{$label}=true";
                $this->utilityBelt->runKubectl($command, $this->token);
    }
}