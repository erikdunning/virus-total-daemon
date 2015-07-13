<?php

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/../logs/sender-error.log");
error_reporting(E_ALL);

require( __DIR__ . '/../vendor/autoload.php');

use VirusTotal\Data;
use VirusTotal\Sender;

/* Instantiate Required Classes and Objects */
$virusTotalSender       = new Sender();
$virusTotalData         = new Data();
$config                 = json_decode( file_get_contents( __DIR__ . '/../config.json') );

$job = $virusTotalData->getQueued();
if( $job ){
    if( intval( $job->attachment_size ) > intval( $config->attachments->maxSize ) ){
        $virusTotalData->markFailure( $job );
        file_put_contents($config->logfile, date('c') . ": Failed job $job->id. Attachment too large.\n", FILE_APPEND);
    } else {
        $virusTotalData->markSending( $job );
        $scanId = $virusTotalSender->sendVirusTotalRequest( $job );
        if( is_string( $scanId ) ){
            $virusTotalData->markPending( $job, $scanId );
            file_put_contents($config->logfile, date('c') . ': Sent job ' . $job->id . ".\n", FILE_APPEND);
        }
    }
}

