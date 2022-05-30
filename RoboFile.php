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
    // define public methods as commands
    public function test() {
        // var_dump(\Migrator\Environment::returnsTrue());

        $cluster = $this->grabCluster();
        /** @var \RenokiCo\PhpK8s\Kinds\K8sNamespace $namespace */
//      foreach ($cluster->getAllNamespaces() as $namespace) {
//          var_dump($namespace->getName());
//        }
//      var_dump($cluster->getConfigmapByName("lagoon-env", 'demo-fsa-dev'));
      $belt = new UtilityBelt($cluster, "demo-fsa-dev");
      $belt->scaleUpDeployment("cli");
      $belt->execInPod("cli", "touch /tmp/hithere");
    }





    /**
     * @return \RenokiCo\PhpK8s\KubernetesCluster
     */
    private function grabCluster() {
      //TODO: this needs to actually not suck
      // Right now we need to push the freaking token in here for out of cluster
      // is there a way of reasonably integrating rancher into this for testing?

//        restCfg := &rest.Config{
//            BearerToken: string(token),
//			Host:        "https://kubernetes.default.svc",
//			TLSClientConfig: rest.TLSClientConfig{
//                Insecure: true,
//			},
//		}

        //Token location: "/var/run/secrets/lagoon/deployer/token"
      $cluster = KubernetesCluster::inClusterConfiguration()->loadTokenFromFile("/var/run/secrets/lagoon/deployer/token");
      return $cluster;
    }
    

}