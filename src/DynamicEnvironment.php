<?php

namespace Migrator;

use function WyriHaximus\Twig\render;

class DynamicEnvironment {

    protected $dynamicEnvironment = [];

    public function fillDynamicEnvironmentFromEnv() {
        $envVars = getenv();
        foreach ($envVars as $key => $val) {
            if($key == "JSON_PAYLOAD") {
                $this->fillDynamicEnvironmentFromJSONPayload($val);
            } else {
                $this->setVariable(sprintf("%s", $key), $val);
            }

        }
    }

    public function fillDynamicEnvironmentFromJSONPayload($payload) {

        $decodedJson = base64_decode($payload);
        if(!$decodedJson) {
            throw new \Exception("Found JSON_PAYLOAD but could not decode it");
        }

        $vars = json_decode($decodedJson, true);

        if(json_last_error() > 0) {
            throw new \Exception(sprintf("Could not decode JSONPAYLOAD: %s ", json_last_error_msg()));
        }

        foreach ($vars as $key => $val) {
            $this->setVariable($key, $val);
        }
    }

    public function setVariable($name, $value) {
        $this->dynamicEnvironment[$name] = $value;
    }

    public function getVariable($name) {
        if(!key_exists($name, $this->dynamicEnvironment)) {
            throw new \Exception("Unable to find variable {$name} in dynamic environment - have you previously set it?");
        }
        return $this->dynamicEnvironment[$name];
    }

    public function getAllVariables() {
        return $this->dynamicEnvironment;
    }

    public function renderText($template, $extraVars = [])
    {
        $subs = array_merge(self::getAllVariables(), $extraVars);
        return render($template, $subs);
    }

}
