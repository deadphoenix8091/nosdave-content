<?php

namespace Nosdave {
    class Config {
        public static function Get($configKey, $fallbackValue = null) {
            global $CliArgs;
            $currentConfigValue = $fallbackValue;
            
            if (isset($_ENV[$configKey])) {
                $currentConfigValue = $_ENV[$configKey];
            } else if($CliArgs) {
                $currentConfigValue = $CliArgs->getArg($configKey);
            }

            return $currentConfigValue;
        }
    }
}