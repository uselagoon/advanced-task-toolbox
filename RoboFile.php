<?php

use League\CLImate\CLImate;
use RenokiCo\PhpK8s\KubernetesCluster;
use Migrator\UtilityBelt;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see https://robo.li/
 */
class RoboFile extends \Robo\Tasks
{

    public function run(
      $opts = [
        'migrateYaml' => './scripts/default.yaml',
        'token' => null, //This is the token used to chat to the k8s api
        'kubeContext' => null,
        'namespace' => null, // The namespace we're targeting in cluster
        'project' => null, //project name, used for lagoon api calls
        'environment' => null, //environment name, used for lagoon api calls
        'sshKey' => null,
      ]
    ) {

        // Bootstrap the environment
        \Migrator\Step\StepParent::fillDynamicEnvironmentFromEnv();

        \Migrator\LagoonUtilityBelt::setUpLagoon_yml();
        $climate = new CLImate;
        $opts = array_merge($opts, $this->processEnvironment());

        $migrateYaml = $opts['migrateYaml'];

        $cluster = $this->grabCluster($opts['token'], $opts['kubeContext']);
        $migration = $this->loadYaml($migrateYaml);

        $args = new \Migrator\RunnerArgs();
        $args->steps = $migration['steps'];
        $args->cluster = $cluster;
        $args->namespace = $this->grabNamespace($opts['namespace']);
        $args->token = $this->getToken($opts['token']);
        $args->project = !empty($opts['project']) ? $opts['project'] : getenv(
          "LAGOON_PROJECT"
        );
        $args->sshKey = !empty($opts['sshKey']) ? $opts['sshKey'] : "/var/run/secrets/lagoon/ssh/ssh-privatekey";
        $args->environment = !empty($opts['environment']) ? $opts['environment'] : getenv(
          "LAGOON_GIT_BRANCH"
        );

        //Here we add a pre-runner set of steps - this can be used for assertions
        //and does _not_ trigger a rollback

        try {
            if(!empty($migration['prerequisites'])) {
                $climate->out("Found prerequisite steps - will run with no rollback\n\n");
                $args->steps = $migration['prerequisites'];
                $runner = new \Migrator\Runner($args);
                $runner->run();
            } else {
                $climate->out("No prerequisites found - proceeding to run main steps\n\n");
            }
        } catch (\Exception $ex) {
            printf("Prerequistes failed with the following message: %s \n\n exiting", $ex->getMessage());
            exit(1);
        }

        try {
            $args->steps = $migration['steps'];
            $runner = new \Migrator\Runner($args);
            $runner->run();
        } catch (\Exception $ex) {

            $climate->border('*');
            $climate->border('*');
            $climate->flank(sprintf("Got error running main steps: %s\n\n", $ex->getMessage()));
            $climate->border('*');
            $climate->border('*');
            if(!empty($migration['rollback'])) {
                $climate->out("Attempting to run rollback steps\n\n");
                $args->steps = $migration['rollback'];
                $runner = new \Migrator\Runner($args);
                $runner->run();
                exit(1);
            }
        }
    }

    private function loadYaml($filename)
    {
        return \Symfony\Component\Yaml\Yaml::parse(
          file_get_contents($filename)
        );
    }


    /**
     * @return array
     */
    protected function processEnvironment() {
        $payload = getenv("JSON_PAYLOAD");
        if(!$payload) return [];
        $payload = base64_decode($payload);
        if(!$payload) return [];

        $payload = json_decode($payload, true);
        if(json_last_error()) {
            var_dump(json_last_error_msg());
            return [];
        }
        return $payload;
    }

    private function grabNamespace($nameSpace)
    {
        if (empty($nameSpace)) {
            return trim(
              file_get_contents(
                "/var/run/secrets/kubernetes.io/serviceaccount/namespace"
              )
            );
        }
        return $nameSpace;
    }

    /**
     * @return \RenokiCo\PhpK8s\KubernetesCluster
     */
    private function grabCluster($token = null, $kubeContext = null)
    {
        if (!empty($token) && !empty($kubeContext)) {
            return KubernetesCluster::fromKubeConfigVariable($kubeContext)
              ->withToken($token);
        }
        $cluster = KubernetesCluster::inClusterConfiguration(
          "https://kubernetes.default.svc.cluster.local"
        )
          ->loadTokenFromFile("/var/run/secrets/lagoon/deployer/token");
        return $cluster;
    }

    /**
     * @return void
     */
    protected function getToken($token = null)
    {
        if (!empty($token)) {
            return $token;
        }
        $inClusterTokenFile = "/var/run/secrets/lagoon/deployer/token";

        if (file_exists($inClusterTokenFile)) {
            return trim(file_get_contents($inClusterTokenFile));
        }
    }


}