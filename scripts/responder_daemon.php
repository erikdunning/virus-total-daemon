<?php

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/../logs/reponder-error.log");
error_reporting(E_ALL);

require( __DIR__ . '/../vendor/autoload.php');

use VirusTotal\Data;
use VirusTotal\Responder;
use VirusTotal\Sender;

/* Instantiate Required Classes and Objects */
$virusTotalSender       = new Sender();
$virusTotalResponder    = new Responder();
$virusTotalData         = new Data();
$config                 = json_decode( file_get_contents( __DIR__ . '/../config.json') );

$job = $virusTotalData->getPending();
if( $job ){
    file_put_contents($config->logfile, 'Querying job ' . $job->id . ".\n", FILE_APPEND);
    $report = $virusTotalSender->getVirusTotalReport( $job ); 
    if( is_string( $report ) ){
        file_put_contents($config->logfile, 'Report for job ' . $job->id . " retrieved.\n", FILE_APPEND);
        $virusTotalData->setReportData( $job, $report );
        $virusTotalData->markSuccess( $job );
        $virusTotalResponder->sendResponse( $job );
    } else if( $report === 0 ){
        file_put_contents($config->logfile, 'Report for job ' . $job->id . " not found.\n", FILE_APPEND);
        $virusTotalData->markFailure( $job );
    } else {
        file_put_contents($config->logfile, 'Report for job ' . $job->id . " still pending.\n", FILE_APPEND);
    }
}

