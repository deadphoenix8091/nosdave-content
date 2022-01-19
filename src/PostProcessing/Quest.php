<?php

namespace Nosdave\PostProcessing {
    use \Nosdave\Logger;

    class Quest extends BasePostProcessing {
        public function Process(&$quests) {
            $questsById = [];

            foreach ($quests as &$currentQuest) {
                $questsById[$currentQuest['internalId']] = &$currentQuest;
            }

            foreach ($quests as &$currentQuest) {
                // if previous quest is set, make sure to set next quest in previous quests data
                if ($currentQuest['questChainPrev'] >= 0) {
                    if (isset($questsById[$currentQuest['questChainPrev']]) && $questsById[$currentQuest['questChainPrev']]['link'] == -1) {
                        $questsById[$currentQuest['questChainPrev']]['link'] = $currentQuest['internalId'];
                    }
                }
                
                // if next quest is set, make sure to set previous quest in next quests data
                if ($currentQuest['link'] >= 0) {
                    if (isset($questsById[$currentQuest['link']])) {
                        Logger::Info("found next quest");

                        if ($questsById[$currentQuest['link']]['questChainPrev'] == -1) {
                            $questsById[$currentQuest['link']]['questChainPrev'] = $currentQuest['internalId'];
                        }
                    } 
                }
            }
        }
    }
}