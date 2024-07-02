<?php

namespace Step;

use Migrator\Step\DynamicEnvironment;
use PHPUnit\Framework\TestCase;
class DynamicEnvironmentTest extends TestCase
{

    /**
     * @return DynamicEnvironmentTraitTestClass
     */
    public function getAndSetupDynamicEnv() {
        $class =  new DynamicEnvironment();
        return $class;
    }

    public function testGetVariable()
    {
        $testClass = $this->getAndSetupDynamicEnv();
        $testClass->setVariable("testval", 555);
        $this->assertEquals(555, $testClass->getVariable("testval"));
    }

    public function testPassingByValue()
    {
        $innerfunc = function(DynamicEnvironment $de) {
            $de->setVariable("setbyinner", true);
        };

        $dynamicEnv = new DynamicEnvironment();
        $innerfunc($dynamicEnv);
        $this->assertTrue($dynamicEnv->getVariable("setbyinner"));
    }
}
