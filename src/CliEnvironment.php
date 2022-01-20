<?php

namespace Nosdave {
    class CliEnvironment {
        public static $CliArgs;

        public static function Bootstrap() {
            $config = [
                // You should specify key as name of option from the command line argument list.
                // Example, name <param-name> for --param-name option
                'output-path' => [
                    'alias' => 'o',
                    'filter' => function($name, $default) {
                        return $name ? mb_convert_case($name, MB_CASE_TITLE, 'UTF-8') : $defult;
                    },
                    'default' => './content',
                ],
                'clear' => [
                    'alias' => 'c',
                    'filter' => function($name, $default) {
                        return !in_array($name, ["false", "no", "n"]);
                    },
                    'default' => false,
                ],
            ];

            self::$CliArgs = new \CliArgs\CliArgs($config);
            if (self::$CliArgs->isFlagExist('help')) {
                echo self::$CliArgs->getHelp();
                die;
            }

            $dotenv = \Dotenv\Dotenv::createImmutable(realpath(__DIR__ . "/.."));
            $dotenv->safeLoad();
        }

        public static function GetArg($configKey) {
            return self::$CliArgs->getArg($configKey);
        }
    }
}