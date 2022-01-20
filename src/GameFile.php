<?php

namespace Nosdave {
    class GameFile {
        public static function lineByLineImporter($fileName, $propertyNames, $lineCallback) {
            $gameDataFileContent = file_get_contents($fileName);
            $gameDataFileContent = explode("\r", $gameDataFileContent);
            $lineCount = count($gameDataFileContent);
            $outputObjects = [];
            $bufferObject = null;
            $nextLineDescription = 0;
            $lastParsedItem = null;
            $alreadyGotDataForKeys = [];

            for($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
                $currentLine = trim($gameDataFileContent[$lineIndex]);

                if ($nextLineDescription > 0) {
                    $currentLine = "description " . $currentLine;
                    $nextLineDescription--;
                }

                // # is the comment indicator usually in those files
                if (mb_substr($currentLine, 0, 1) == "#" || mb_strlen($currentLine) < 1) {
                    continue;
                }
                
                $currentImportMode = "default";
                if ($currentLine[0] == ".") {
                    //Logger::Debug("Switching to dotline import mode.");
                    $currentImportMode = 'dotline';
                } else if (is_numeric($currentLine[0])) {
                    //Logger::Debug("Switching to linebyline import mode.");
                    $currentImportMode = "linebyline";
                }

                // default import mode means we add properties to objects until we end them
                // dotline import is unknown
                // linebyline import mode means one output object per line

                $flushBufferObject = function () use (&$bufferObject, &$outputObjects, &$alreadyGotDataForKeys) {
                    $alreadyGotDataForKeys = [];
                    if ($bufferObject) {
                        array_push($outputObjects, $bufferObject);
                    }
                    $bufferObject = [];
                    $lastParsedItem = &$bufferObject;
                };

                if ($currentImportMode == "default") {
                    if (in_array(mb_strtolower($currentLine), ['end', 'e'])) {
                        $flushBufferObject();
                        continue;
                    }

                    $pattern = '/[ \ \t]/u';
                    $commandArgs = preg_split($pattern, $currentLine);

                    if (is_array($commandArgs) && mb_strtolower($commandArgs[0]) == 'linedesc') {
                        $nextLineDescription = 1;
                        continue;
                    }

                    if (is_array($commandArgs) && mb_strtolower($commandArgs[0]) == 'z_desc') {
                        $nextLineDescription = intval($commandArgs[1]);
                        continue;
                    }

                    if (is_array($commandArgs) && 
                        (mb_strtolower($commandArgs[0]) == 'vnum'/* ||
                        mb_strtolower($commandArgs[0]) == 's'*/)) {
                            //var_dump("here");
                        $flushBufferObject();
                    }

                    $currentPropData = $lineCallback($currentImportMode, $currentLine, $lastParsedItem);
                    if (!$currentPropData) {
                        $flushBufferObject();
                        continue;
                    }

                    if ($bufferObject == null) {
                        $bufferObject = [];
                        $lastParsedItem = &$bufferObject;
                    }

                    if ($currentPropData['type'] == 'single'){
                        $hasBeenSetMultipleTimesBefore = (1 < count(
                            array_filter(
                                $alreadyGotDataForKeys, 
                                function ($currentKey) use ($currentPropData) { 
                                    return $currentKey == $currentPropData['key']; 
                                }
                            )
                        ));
                        
                        if (isset($bufferObject[$currentPropData['key']])) {
                            if (!$hasBeenSetMultipleTimesBefore)
                                $bufferObject[$currentPropData['key']] = [$currentPropData['value']];
                            else
                                array_push($bufferObject[$currentPropData['key']], $currentPropData['value']);
                        } else {
                            $bufferObject[$currentPropData['key']] = $currentPropData['value'];
                        }

                        array_push($alreadyGotDataForKeys, $currentPropData['key']);
                    } else if ($currentPropData['type'] == 'multi') {
                        foreach($currentPropData['data'] as $currKeyValue) {
                            $bufferObject[$currKeyValue['key']] = $currKeyValue['value'];
                            array_push($alreadyGotDataForKeys, $currKeyValue['key']);
                        }
                    }

                } else {
                    $bufferObject = $lineCallback($currentImportMode, $currentLine, $lastParsedItem);
                    $flushBufferObject();
                }
            }

            return $outputObjects;
        }

        public static function parseDatFile($fileName, $inputName, &$translations, &$postProcessingCallbacks) {
            global $writtenFileCount;

            $foundOpcodes = [];
            $propertyNamesByInputName = [
                'MapIDData' => ['sourceMapId', 'targetMapId', 'sourceMapPoint', 'targetMapPoint']
            ];
            $propertyNames = null;
            if (isset($propertyNamesByInputName[$inputName])) {
                $propertyNames = $propertyNamesByInputName[$inputName];
            }

            $outputObjects = self::lineByLineImporter($fileName, $propertyNames, function ($importMode, $currentLine, $propertyNames) use (&$foundOpcodes, &$translations, &$inputName) {
                //Logger::Debug("current line: \" . $currentLine . \"");
                $pattern = '/[ \ \t]/u';
                $commandArgs = preg_split($pattern, $currentLine);

                if ($commandArgs === false) {
                    return;
                }

                if ($importMode != "default") {
                    // we got into lbl mode
                    if (isset($propertyNames) && count($propertyNames) == count($commandArgs)) {
                        $outObj = [];
                        for($i = 0; $i < count($propertyNames); $i++) {
                            $outObj[$propertyNames[$i]] = $commandArgs;
                        }
                    }
                    return $commandArgs;
                }

                $opcode = mb_strtolower($commandArgs[0]);
                array_push($foundOpcodes, $opcode);

                array_shift($commandArgs);
                reset($commandArgs);
                
                $commandArgs = array_map(function ($currentElement) use (&$translations) {
                    if (intval($currentElement)."" == $currentElement) {
                        return intval($currentElement);
                    }
                    return LocalizationHelper::Translate($translations, $currentElement);
                }, $commandArgs);

                switch($opcode) {
                    case "begin":
                        return;
                    case "vnum":
                    case "s";
                        $internalId = intval($commandArgs[0]);
                        array_shift($commandArgs);
                        reset($commandArgs);
                        
                        if (count($commandArgs) == 0) {
                            return ["type" => "multi", "data" => [
                                ['key' => "internalId", 'value' => $internalId]
                            ]];
                        } else {
                            if ($inputName == "quest") {
                                $questChainRoot = intval($commandArgs[2]);
                                $questChainPrev = intval($commandArgs[3]);
            
                                return ["type" => "multi", "data" => [
                                    ['key' => $opcode, 'value' => $commandArgs],
                                    ['key' => "questChainRoot", 'value' => $questChainRoot],
                                    ['key' => "questChainPrev", 'value' => $questChainPrev],
                                    ['key' => "internalId", 'value' => $internalId]
                                ]];
                            } else {
                                return ["type" => "multi", "data" => [
                                    ['key' => $opcode, 'value' => $commandArgs],
                                    ['key' => "internalId", 'value' => $internalId]
                                ]];
                            }
                        }
                    case "d": //dataline maybe?!
                        return ["type" => "single", 'key' => 'data', 'value' => $commandArgs];
                    case "data":
                    case "A":
                        if ($inputName == "act_desc") {
                            return [
                                "type" => "single", 
                                'key' => ($opcode == "A") ? "titles" : "subacts",
                                'value' => $commandArgs
                            ];
                        } else if (in_array($inputName, ["Skill", "quest", 'Item'])) {
                            return [
                                "type" => "single", 
                                'key' => "data",
                                'value' => $commandArgs
                            ];
                        } else {
                            $lastParsedItem['data'] = $commandArgs;
                            return;
                        }
                    default:
                        if ($opcode == "description") {
                            return [
                                "type" => "single", 
                                'key' => $opcode, 
                                'value' => LocalizationHelper::Translate($translations, mb_substr($currentLine, mb_strlen("description ")))
                            ];
                        } else {
                            return [
                                "type" => "single", 
                                'key' => $opcode, 
                                'value' => (count($commandArgs) == 1) ? $commandArgs[0] : $commandArgs
                            ];
                        }
                }
            });

            Logger::Info("Processed " . count($outputObjects) . " objects in memory");

            if (isset($postProcessingCallbacks[$inputName])) {
                Logger::Info("Starting postprocessing for \"" . $inputName . "\".");
                $postProcessingCallbacks[$inputName]($outputObjects);
                Logger::Info("Finished postprocessing for \"" . $inputName . "\".");
            } else {
                Logger::Info("No postprocessing callback for \"" . $inputName . "\"");
            }

            Logger::Info("Writing objects to disk");

            $totalSlugs = [];
            $outputFileDir = Config::Get('OUTPUT_DIR', './output') . '/' . mb_strtolower($inputName);

            if (!is_dir($outputFileDir)) {
                mkdir($outputFileDir, 0777, true);
            }
            
            foreach($outputObjects as $currentOutputObject) {
                $slug = Sluggify::BuildUniqueSlug($currentOutputObject, $totalSlugs);

                $currentOutputObject['slug'] = $slug;
                $outputFilePath = $outputFileDir . '/' . $slug . '.json';

                if (file_exists($outputFilePath)) {
                    Logger::Debug("Output file name: \"" . $outputFilePath . "\"");
                    Logger::Error("Output file already exists, that means creating nonunique slugs or non clean content directory.");
                }

                NuxtOutput::Sanitize($currentOutputObject);

                file_put_contents($outputFileDir . '/' . $slug . '.json', json_encode($currentOutputObject, JSON_PRETTY_PRINT), LOCK_EX);
            }

            $foundOpcodes = array_unique($foundOpcodes);
            
            $opcodeDumpFolderPath = Config::Get('OUTPUT_DIR', './content') . '/opcodes';
            if (!is_dir($opcodeDumpFolderPath)) {
                mkdir($opcodeDumpFolderPath, 0777, true);
            }
            file_put_contents($opcodeDumpFolderPath . '/' . $inputName . '.json', json_encode($foundOpcodes, JSON_PRETTY_PRINT), LOCK_EX);

            if (count($outputObjects) == -2 + count(scandir($outputFileDir))) {
                Logger::Info("Output file count matching generated object data.");
            } else {
                Logger::Error("Output file count mismatch! Some objects may not export correctly.");
            }
        }

        public static function Parse($fileName, &$postProcessingCallbacks) {
            $inputName = basename($fileName);
            $inputName = str_replace(".dat", "", $inputName);

            if (mb_strpos($inputName, "_") !== false) {
                if (mb_strpos($inputName, "de_") === false && mb_strpos($inputName, "act_") === false) {
                    Logger::Info("Skipping \"". $inputName . "\"");
                    return;
                }
            }

            //skip npctalk as its broken at the moment
            if (true) { // leave this snippet here for debugging, oneshot script is not that performance relevant
                if (mb_strpos($inputName, "npctalk") !== false) {
                    Logger::Info("Skipping \"". $inputName . "\"");
                    return;
                }
            }

            //debugging, skip everything but XXX
            if (false) { // leave this snippet here for debugging, oneshot script is not that performance relevant
                if (mb_strpos($inputName, "Item") === false) {
                    Logger::Info("Skipping \"". $inputName . "\"");
                    return;
                }
            }

            Logger::Info("Starting to parse \"". $inputName . "\"");
            $langDataPath = Config::Get('EXTRACTED_LANGUAGE_DIR', './clientfiles/NSlangData_DE');
            $translationsFileName = $langDataPath . '/_code_de_' . $inputName . '.dat.bin';
            if (!file_exists($translationsFileName)) {
                $translationsFileName = $langDataPath . '/_code_de_' . $inputName . '.lst.bin';
            }
            if (!file_exists($translationsFileName)) {
                $translationsFileName = $langDataPath . '/_code_de_' . $inputName . '.txt.bin';
            }

            $translations = LocalizationHelper::Load($inputName);

            if (mb_strpos($fileName, ".dat") !== false) {
                self::parseDatFile($fileName, $inputName, $translations, $postProcessingCallbacks);
            } else {
                Logger::Info("Parser for \"". $inputName . "\" not yet implemented.");
            }

            Logger::Info("Completed parsing \"". $inputName . "\"\n\r");
        }
    }
}
