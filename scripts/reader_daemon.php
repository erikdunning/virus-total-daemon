<?php

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/../logs/reader-error.log");
error_reporting(E_ALL);

require( __DIR__ . '/../vendor/autoload.php');

use VirusTotal\Data;
use VirusTotal\Reader;
use VirusTotal\Sender;
use VirusTotal\Responder;

/* Instantiate Required Classes and Objects */
$virusTotalReader       = new Reader();
$virusTotalData         = new Data();
$config                 = json_decode( file_get_contents( __DIR__ . '/../config.json') );
$startDate              = new DateTime( $argv[1] );

/* Download Messages / Attachments and Create Jobs */
$messages = $virusTotalReader->getMessages( $startDate );
foreach( $messages as $msg ){
    $attachmentItems = $virusTotalReader->downloadAttachments( $msg );
    $virusTotalData->createJobs( $msg, $attachmentItems );
}

file_put_contents($config->logfile, 'Read ' . sizeof( $messages ) . " messages.\n", FILE_APPEND);

