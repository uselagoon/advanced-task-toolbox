<?php

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
        'token' => null,
        'kubeContext' => null,
        'namespace' => null,
        'project' => null,
        'environment' => null,
        'sshKey' => null,
      ]
    ) {

        \Migrator\LagoonUtilityBelt::setUpLagoon_yml();

        $opts = array_merge($opts, $this->processEnvironment());

        $migrateYaml = $opts['migrateYaml'];
        var_dump($migrateYaml);

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

        try {
            $runner = new \Migrator\Runner($args);
            $runner->run();
        } catch (\Exception $ex) {
            printf("Got error running main steps: %s\n\n", $ex->getMessage());
            if(!empty($migration['rollback'])) {
                printf("Attempting to run rollback steps\n\n");
                $args->steps = $migration['rollback'];
                $runner = new \Migrator\Runner($args);
                $runner->run();
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
//        var_dump($payload);

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