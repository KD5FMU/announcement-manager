<?php

/*
 * Updated June 12, 2026
 * Modified by N5AD
 */


$files = [];


// Find .mp3 files

$mp3_files = glob("/mp3/*.mp3");

foreach ($mp3_files as $f) {

    $files[] = basename($f);

}


// Find .wav files

$wav_files = glob("/mp3/*.wav");

foreach ($wav_files as $f) {

    $files[] = basename($f);

}
// Sort alphabetically for nicer display

sort($files);


// Remove duplicates (in case someone has both .mp3 and .wav with same base name)

$files = array_unique($files);


header('Content-Type: application/json');

echo json_encode($files);

?>
