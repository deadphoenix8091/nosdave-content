<?php

namespace Nosdave {
    class LocalizationHelper {
        public static function Translate(&$translations, $text) {
            $ztsPos = mb_strpos($text, "zts");
            if ($ztsPos !== false) {

                //first check direct match, then try the brutehammer approach
                if (isset($translations[$text])) {
                    return $translations[$text];
                }

                while(($ztsPos = mb_strpos($text, "zts")) !== false) {
                    //as long as we dont replace it out it stays there
                    //read to next " " or line end
                    $whiteSpacePosAfterZTS = mb_strpos($text, " ", $ztsPos);
                    if ($whiteSpacePosAfterZTS !== false) {
                        $translationKey = mb_substr($text, $ztsPos, $whiteSpacePosAfterZTS - $ztsPos);
                    } else {
                        $translationKey = mb_substr($text, $ztsPos);
                    }
                    
                    if (isset($translations[$translationKey])) {
                        $text = str_replace($translationKey, $translations[$translationKey], $text);
                    } else {
                        $text = str_replace($translationKey, str_replace("zts", "unknowntxt_", $translationKey), $text);
                    }
                }
            }

            return $text;
        }

        public static function Load($inputName) {
            $langDataPath = Config::Get('EXTRACTED_LANGUAGE_DIR', './clientfiles/NSlangData_DE');
            $inputFileName = $langDataPath . '/_code_de_' . $inputName . '.txt';
            Logger::Verbose("Attempting to load translation dictionary from \"" . $inputFileName . "\".");

            if (!file_exists($inputFileName)) {
                return [];
            }

            Logger::Verbose("Found translation dictionary.");

            $translationStringDataRaw = file_get_contents($inputFileName);
            $translationStringDataRaw = explode("\r", $translationStringDataRaw);
            $translations = [];

            foreach($translationStringDataRaw as $currentTranslation) {
                $needlePos = mb_strpos($currentTranslation, "\t");

                $translationKey = mb_substr($currentTranslation,  0, $needlePos);
                $translationValue = mb_substr($currentTranslation, $needlePos + 1);

                // note currently we only verified that this is the proper german code conversion
                $translationValue = iconv( "Windows-1250", "UTF-8", ($translationValue));

                $translations[$translationKey] = $translationValue;
            }

            return $translations;
        }
    }
}