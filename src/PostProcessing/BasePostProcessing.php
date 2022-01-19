<?php

namespace Nosdave\PostProcessing {
    class BasePostProcessing {
        public function Process(&$objects) {
            //this function needs to be overriden
        }

        public static function GetCallbackFunc() {
            $processingInstance = new static();
            return function (&$objects) use ($processingInstance) {
                $processingInstance->Process($objects);
            };
        }
    }
}