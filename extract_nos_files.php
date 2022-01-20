<?php

require_once('./vendor/autoload.php');

use Nosdave\Logger;

$timeStarted = microtime(true);

Nosdave\CliEnvironment::Bootstrap();

Logger::Info("Scanning NostaleData directory...\n");
$nostaleDataDir = Nosdave\Config::Get("NOSTALEDATA_DIR");
Logger::Info("NostaleData directory: \"" . $nostaleDataDir . "\"");
$gameDataFiles = scandir($nostaleDataDir);
$gameDataFiles = array_filter($gameDataFiles, function ($currentDataFileName) use ($nostaleDataDir) {
    return $currentDataFileName != "." && $currentDataFileName != ".." && !is_dir($nostaleDataDir . '/' . $currentDataFileName);
});

function decryptNosData(&$array) {
    $decryptedFile = "";
    $cryptoArray = [0x00, 0x20, 0x2D, 0x2E, 0x30, 0x31, 0x32, 0x33, 0x34, 0x35, 0x36, 0x37, 0x38, 0x39, 0x0A, 0x00];
    $currIndex = 0;
    while ($currIndex < strlen($array)) {
        $currentByte = ord($array[$currIndex]);
        $currIndex++;
        if ($currentByte == 0xFF) {
            $decryptedFile .= chr(0xD);
            continue;
        }
        $validate = $currentByte & 0x7F;
        if ($currentByte & 0x80) {
            for (; $validate > 0; $validate -= 2) {
                if ($currIndex >= strlen($array))
                    break;
                $currentByte = ord($array[$currIndex]);
                $currIndex++;
                $firstByte = $cryptoArray[($currentByte & 0xF0) >> 4];
                $decryptedFile .= chr($firstByte);
                if ($validate <= 1)
                    break;
                $secondByte = $cryptoArray[$currentByte & 0xF];
                if (!$secondByte)
                    break;
                $decryptedFile .= chr($secondByte);
            }
        } else {
            for (; $validate > 0; --$validate) {
                if ($currIndex >= strlen($array))
                    break;
                $currentByte = ord($array[$currIndex]);
                $currIndex++;
                $decryptedFile .= chr($currentByte ^ 0x33);
            }
        }
    }
    return $decryptedFile;
}

foreach($gameDataFiles as $currentDataFileName) {
    $fileSize = filesize($nostaleDataDir . '/' . $currentDataFileName);
    $fhandle = fopen($nostaleDataDir . '/' . $currentDataFileName, 'r');
    fseek($fhandle, 0);
    $magic = fread($fhandle, 4);

    if (!in_array($currentDataFileName, ['NSgtdData.NOS', 'NSlangData_DE.NOS'])) continue;

    //if (header.mid(0, 7) == "NT Data" || header.mid(0, 10) == "32GBS V1.0" || header.mid(0, 10) == "ITEMS V1.0")
    //return &zlibOpener;
    //else if (header.mid(0, 11) == "CCINF V1.20")
    //    return &ccinfOpener;
    switch($magic) {
        case "NT D": //???
        case "32GB": //???
        case "CCIN": //???
            //zlib nos archives...
            fseek($fhandle, 0);
            fseek($fhandle, 0x10);
            Logger::info("reading archive that we want to read yay " . $currentDataFileName);
            $header = unpack("V1filecount", fread($fhandle, 4));
            $seperator = fread($fhandle, 1);
            $canRead = function ($count) use ($fhandle, $fileSize) {
                $currentPosition = ftell($fhandle);
                return ($currentPosition + $count <= $fileSize);
            };

            for ($i = 0; $i < $header["filecount"]; $i++) {
                if (!$canRead(8)){
                    Logger::Debug("Unexpected end of file");
                    break;
                }
                $fileHeader = unpack("V1id/V1offset", fread($fhandle, 8));
                $previousOffset = ftell($fhandle);
                fseek($fhandle, $fileHeader['offset']);
                
                if (!$canRead(13)){
                    Logger::Debug("Unexpected end of file");
                    break;
                }
                $fileHeader = array_merge($fileHeader, @unpack("V1creationdate/V1dataSize/V1compressedDataSize/C1isCompressed", fread($fhandle, 13)));
                $fileContent = "";

                if ($fileHeader['compressedDataSize'] > 0) {
                    if (!$canRead($fileHeader['compressedDataSize'])){
                        Logger::Debug("Unexpected end of fileeeee");
                        break;
                    }
                    $fileContent = fread($fhandle, $fileHeader['compressedDataSize']);

                    if ($fileHeader['isCompressed'] == 1) {
                        //decompress
                        $fileContent = zlib_decode($fileContent);
                    }
                }

                if (!is_dir($nostaleDataDir . "/ext_" . $currentDataFileName))
                    mkdir($nostaleDataDir . "/ext_" . $currentDataFileName);

                $fileName = $fileHeader['id'] . '.raw';
                file_put_contents($nostaleDataDir . "/ext_" . $currentDataFileName . "/" . $fileName, $fileContent);

                fseek($fhandle, $previousOffset);
            }

            Logger::info("Extracted " . $i . " files.");
            break;
        default:
            Logger::info("reading archive that we want to read yay " . $currentDataFileName);
            $header = unpack("L1filecount", $magic);

            for ($i = 0; $i < $header["filecount"]; $i++) {
                $fileHeader = unpack("L1id/L1namelength", fread($fhandle, 8));
                $fileName = fread($fhandle, $fileHeader['namelength']);
                $fileHeader = array_merge($fileHeader, unpack("L1isDat/L1filesize", fread($fhandle, 8)), ['name' => $fileName]);
                $fileContent = "";
                if ($fileHeader['filesize'] > 0) {
                    $fileContent = fread($fhandle, $fileHeader['filesize']);
                    Logger::Info("Extracting \"" . $fileName . "\"");

                    if ($fileHeader['isDat'] == 1) {
                        //we need to do decryption
                        $fileContent = decryptNosData($fileContent);
                    } else {
                        $array = $fileContent;
                        $fileContent = "";
                        $lines = unpack("L1lines", substr($array, 0, 4))['lines'];
                        $pos = 4;
                        for ($i = 0; $i < $lines; $i++) {
                            $strLen = unpack("L1strlen", substr($array, $pos, 4))['strlen'];
                            $pos += 4;
                            $str = substr($array, $pos, $strLen);
                            $pos += $strLen;
                            for ($idx = 0; $idx < strlen($str); $idx++)
                                $fileContent .= ord($str[$idx]) ^ 0x1;
                            $fileContent .= '\n';
                        }
                    }
                }

                if (!is_dir($nostaleDataDir . "/ext_" . $currentDataFileName))
                    mkdir($nostaleDataDir . "/ext_" . $currentDataFileName);
                file_put_contents($nostaleDataDir . "/ext_" . $currentDataFileName . "/" . $fileName, $fileContent);
            }
            break;
    }

    fclose($fhandle);
}

//lets start implementing a scan over all .nos files
// and dumping header info for each .nos file
