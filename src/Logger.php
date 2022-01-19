<?php

namespace Nosdave {
    class Logger {
        public static $LOG_LEVEL_ERROR = 0;
        public static $LOG_LEVEL_INFO = 1;
        public static $LOG_LEVEL_SUCCESS = 2;
        public static $LOG_LEVEL_DEBUG = 3;
        public static $LOG_LEVEL_VERBOSE = 4;

        static $cliLogLevelWidth = 10;
        static $cliLogLevelMinPriority = 0;
        static $initialized = false;

        static $cliLogLevels = [
            [//0
                'name' => 'Error',
                'priority' => 100
            ], [//1
                'name' => 'Info',
                'priority' => 50
            ], [//2
                'name' => 'Success',
                'priority' => 50
            ], [//3
                'name' => 'Debug',
                'priority' => 10
            ], [//4
                'name' => 'Verbose',
                'priority' => 1
            ]
        ];

        private static function Initialize() {
            if (self::$initialized) {
                return;
            }

            self::$cliLogLevelMinPriority = Config::Get('LOG_MIN_PRIORITY', 10);

            self::$initialized = true;
        }

        private static function FormatLogLevel($logLevel) {
            self::Initialize();

            $outBuffer = "[" . $logLevel .  "]";
            if (mb_strlen($outBuffer) != self::$cliLogLevelWidth) {
                $outBuffer = mb_substr($outBuffer, 0, self::$cliLogLevelWidth);
                $outBuffer = str_repeat(" ", 10 - mb_strlen($outBuffer)) . $outBuffer;
            }

            return $outBuffer;
        }

        private static function WriteLine($logLevel, $message) {
            self::Initialize();

            $logLevelConfig = self::$cliLogLevels[$logLevel];

            if ($logLevelConfig['priority'] < self::$cliLogLevelMinPriority) {
                return;
            }

            echo self::FormatLogLevel($logLevelConfig['name']) . ": ".$message.PHP_EOL;
        }

        public static function Error($message) {
            self::WriteLine(self::$LOG_LEVEL_ERROR, $message);
        }
        public static function Success($message) {
            self::WriteLine(self::$LOG_LEVEL_SUCCESS, $message);
        }
        public static function Info($message) {
            self::WriteLine(self::$LOG_LEVEL_INFO, $message);
        }
        public static function Debug($message) {
            self::WriteLine(self::$LOG_LEVEL_DEBUG, $message);
        }
        public static function Verbose($message) {
            self::WriteLine(self::$LOG_LEVEL_VERBOSE, $message);
        }
    }
}
