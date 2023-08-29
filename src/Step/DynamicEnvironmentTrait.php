<?php

namespace Migrator\Step;

use function WyriHaximus\Twig\render;

trait DynamicEnvironmentTrait {

    static $dynamicEnvironment = [];

    public static function fillDynamicEnvironmentFromEnv() {
        $envVars = getenv();
        foreach ($envVars as $key => $val) {
            if($key == "JSON_PAYLOAD") {
                self::fillDynamicEnvironmentFromJSONPayload($val);
            } else {
                self::setVariable(sprintf("%s", $key), $val);
            }

        }
    }

    public static function fillDynamicEnvironmentFromJSONPayload($payload) {

        $decodedJson = base64_decode($payload);
        if(!$decodedJson) {
            throw new \Exception("Found JSON_PAYLOAD but could not decode it");
        }

        $vars = json_decode($decodedJson, true);

        if(json_last_error() > 0) {
            throw new \Exception(sprintf("Could not decode JSONPAYLOAD: %s ", json_last_error_msg()));
        }

        foreach ($vars as $key => $val) {
            self::setVariable($key, $val);
        }
    }

    public static function setVariable($name, $value) {
        self::$dynamicEnvironment[$name] = $value;
    }

    public static function getVariable($name) {
        if(!key_exists($name, self::$dynamicEnvironment)) {
            throw new \Exception("Unable to find variable {$name} in dynamic environment - have you previously set it?");
        }
        return self::$dynamicEnvironment[$name];
    }

    public static function getAllVariables() {
        return self::$dynamicEnvironment;
    }

    public static function renderText($template, $extraVars = [])
    {
        $subs = array_merge(self::getAllVariables(), $extraVars);
        return render($template, $subs);
    }

}
