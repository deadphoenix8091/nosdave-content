<?php

require_once('./vendor/autoload.php');


use Nosdave\Logger;
use Nosdave\GameFile;
use Nosdave\PostProcessing\Quest;

$timeStarted = microtime(true);

Nosdave\CliEnvironment::Bootstrap();

$clearOutputDir = Nosdave\Config::Get('clear');
$outputDir = realpath(Nosdave\Config::Get('OUTPUT_DIR', "./content"));

Logger::Info("Output directory: \"" . $outputDir . "\"");

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

if ($clearOutputDir) {
    Logger::Info("Removing existing output directory as specified by --clear.");
    system("rm -rf " . $outputDir);
}

mkdir($outputDir, 0777, true);
Logger::Info("Creating output directory.");

//logtest
/*
Logger::Verbose("This message is for detailed verbose info only.");
Logger::Debug("This message is for debugging purposes.");
Logger::Info("This message is for state information changes.");
Logger::Success("This message is for reporting success.");
Logger::Error("This message is signaling unexpected conditions.");
exit();
*/

// lets get a list of files available for importing
$gameDataDir = Nosdave\Config::Get('EXTRACTED_GAME_TABLE_DATA_DIR');

if (!is_dir($gameDataDir)) {
    Logger::Error("Game datafiles directory does not exist");
}

Logger::Info("Scanning game datafiles directory...\n");
$gameDataFiles = scandir($gameDataDir);
$gameDataFiles = array_filter($gameDataFiles, function ($currentDataFileName) {
    return $currentDataFileName != "." && $currentDataFileName != "..";
});

//$maxProcessingThreads = 4;
//Way easier to debug if processing is done sequentially
$maxProcessingThreads = 1;
$activeProcessingThreads = 0;
$processingThreadPids = [];
$processQueue = [];

$postProcessingCallbacks = [
    'quest' => Quest::GetCallbackFunc()
];

foreach($gameDataFiles as $currentGameDataFileName) {
    //abuse of anonymous functions to keep variables in correct scope...
    $newProcessQueueItem = (function ($gameDataDir, $currentGameDataFileName) use (&$postProcessingCallbacks) {
        return $newProcessQueueItem = [
            'gameDataFileName' => $currentGameDataFileName,
            'callback' => function () use ($gameDataDir, $currentGameDataFileName, &$postProcessingCallbacks) {
                //processing thread payload
                $pathToGameDataFile = realpath($gameDataDir . "/" . $currentGameDataFileName);
                GameFile::Parse($pathToGameDataFile, $postProcessingCallbacks); 
            }
        ];
    })($gameDataDir, $currentGameDataFileName);
   
    array_push($processQueue, $newProcessQueueItem);
}

// start new processing threads
$fillUpWorkerThreads = function () use (
    &$activeProcessingThreads, 
    &$maxProcessingThreads, 
    &$processQueue, 
    &$processingThreadPids) {
    while($activeProcessingThreads < $maxProcessingThreads && count($processQueue) > 0) {
        $processQueueItem = array_shift($processQueue);
        reset($processQueue);

        switch ($pid = pcntl_fork()) {
            case -1:
                Logger::Error('Fork failed');
                exit(0);
            case 0:
                $processQueueItem['callback']();
                //killthread
                exit(65280);
                break;
            default:
                $activeProcessingThreads++;
                array_push($processingThreadPids, $pid);
                break;
        }
    }
};

$fillUpWorkerThreads();

//wait for active threads to finish and start new ones 
while($activeProcessingThreads > 0) {
    $pidToWaitFor = array_shift($processingThreadPids);
    pcntl_waitpid($pidToWaitFor, $status);
    $activeProcessingThreads--;
    $fillUpWorkerThreads();
}

$timeDelta = microtime(true) - $timeStarted;

$outputDir = Nosdave\Config::Get('OUTPUT_DIR');
$outputFileCount = system('find ' . $outputDir . ' | wc -l');

Logger::Success("Object dumping completed.");
Logger::Info("Written ". $outputFileCount ." files");
Logger::Info("Took ". floor($timeDelta / 60) ." minutes ". $timeDelta % 60 ." seconds");


