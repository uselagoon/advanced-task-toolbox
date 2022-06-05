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

    public function run(string $migrateYaml, $opts = ['token' => null, 'kubeContext' => null, 'namespace' => null]) {
        $cluster = $this->grabCluster($opts['token'], $opts['kubeContext']);
        $migration = $this->loadYaml($migrateYaml);
        $runner = new \Migrator\Runner($migration['steps'], $cluster, $this->grabNamespace($opts['namespace']), $this->getToken($opts['token']));
        $runner->run();
    }

    private function loadYaml($filename) {
        return \Symfony\Component\Yaml\Yaml::parse(file_get_contents($filename));
    }

    // define public methods as commands
    public function test($opts = ['token' => null, 'kubeContext' => null, 'namespace' => null])
    {
        // var_dump(\Migrator\Environment::returnsTrue());
//        sleep(60*10);
        $cluster = $this->grabCluster($opts['token'], $opts['kubeContext']);
        /** @var \RenokiCo\PhpK8s\Kinds\K8sNamespace $namespace */
        //      foreach ($cluster->getAllNamespaces() as $namespace) {
        //          var_dump($namespace->getName());
        //        }
        //      var_dump($cluster->getConfigmapByName("lagoon-env", 'demo-fsa-dev'));

        $belt = new \Migrator\LagoonUtilityBelt($cluster, $this->grabNamespace($opts['namespace']));
//        var_dump($belt->getLagoonToken());
//        $belt->deployEnvironment("demo-fsa", "main");
////        $belt->scaleUpDeployment("cli");
////        $belt->execInPod("cli", "touch /tmp/hithere");
//        $this->getToken();
    }


    private function grabNamespace($nameSpace) {
        if(empty($nameSpace)) {
            return trim(file_get_contents("/var/run/secrets/kubernetes.io/serviceaccount/namespace"));
        }
        return $nameSpace;
    }

    /**
     * @return \RenokiCo\PhpK8s\KubernetesCluster
     */
    private function grabCluster($token = null, $kubeContext = null)
    {
        if(!empty($token) && !empty($kubeContext)) {
            return KubernetesCluster::fromKubeConfigVariable($kubeContext)
              ->withToken($token);
        }
        $cluster = KubernetesCluster::inClusterConfiguration("https://kubernetes.default.svc.cluster.local")
          ->loadTokenFromFile("/var/run/secrets/lagoon/deployer/token");
        return $cluster;
    }

    /**
     * @return void
     */
    protected function getToken($token = null)
    {
        if(!empty($token)) {
            return $token;
        }
        $inClusterTokenFile = "/var/run/secrets/lagoon/deployer/token";

        if (file_exists($inClusterTokenFile)) {
            return trim(file_get_contents($inClusterTokenFile));
        }
    }


}