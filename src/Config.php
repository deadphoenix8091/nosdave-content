<?php

namespace Nosdave {
    class Config {
        public static function Get($configKey, $fallbackValue = null) {
            global $CliArgs;
            $currentConfigValue = $fallbackValue;
            
            if (isset($_ENV[$configKey])) {
                return $_ENV[$configKey];
            } else {
                return CliEnvironment::GetArg($configKey);
            }

            return $currentConfigValue;
        }
    }
}