<?php

namespace Nosdave {
    class NuxtOutput {
        public static function Sanitize(&$currentItem) {
            $fulltextSearchFields = ['title', 'description', 'slug', 'text'];
            foreach($fulltextSearchFields as $currentFieldToAvoid) {
                if (isset($currentItem[$currentFieldToAvoid])) {
                    $currentItem['raw_' . $currentFieldToAvoid] = $currentItem[$currentFieldToAvoid];
                    unset($currentItem[$currentFieldToAvoid]);
                }
            }
        }
    }
}