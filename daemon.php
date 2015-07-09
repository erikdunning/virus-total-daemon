<?php

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/php-error.log");
error_reporting(E_ALL);

require 'vendor/autoload.php';

use VirusTotal\Data;
use VirusTotal\Reader;
use VirusTotal\Sender;
use VirusTotal\Responder;

while( true ){

    /* Instantiate Required Classes and Objects */
    $virusTotalReader       = new Reader();
    $virusTotalSender       = new Sender();
    $virusTotalResponder    = new Responder();
    $virusTotalData         = new Data();
    $startDate              = isset( $startDate ) ? $startDate : (new DateTime())->setTimestamp(time() - ( 24 * 60 * 60 ));
    $lastBegan              = (new DateTime())->setTimestamp(time() - 60);
    $config                 = json_decode( file_get_contents( __DIR__ . '/config.json') );

    /* Download Messages / Attachments and Create Jobs */
    $messages = $virusTotalReader->getMessages( $startDate );
    foreach( $messages as $msg ){
        $attachmentItems = $virusTotalReader->downloadAttachments( $msg );
        $virusTotalData->createJobs( $msg, $attachmentItems );
    }

    echo 'Read ' . sizeof( $messages ) . " messages.\n";

    $startDate = $lastBegan;

    /* Perform Sender Operation */
    $job = $virusTotalData->getQueued();
    if( $job ){
        if( intval( $job->attachment_size ) > intval( $config->attachments->maxSize ) ){
            $virusTotalData->markFailure( $job );
            echo "Failed job $job->id. Attachment too large.\n";
        } else {
            $virusTotalData->markSending( $job );
            $scanId = $virusTotalSender->sendVirusTotalRequest( $job );
            if( is_string( $scanId ) ){
                $virusTotalData->markPending( $job, $scanId );
                echo 'Sent job ' . $job->id . ".\n";
            }
        }
    }

    sleep(15);

    /* Perform Responder Operation */
    $job = $virusTotalData->getPending();
    if( $job ){
        echo 'Querying job ' . $job->id . ".\n";
        $report = $virusTotalSender->getVirusTotalReport( $job ); 
        if( is_string( $report ) ){
            echo 'Report for job ' . $job->id . " retrieved.\n";
            $virusTotalData->setReportData( $job, $report );
            $virusTotalData->markSuccess( $job );
            $virusTotalResponder->sendResponse( $job );
        } else if( $report === 0 ){
            echo 'Report for job ' . $job->id . " not found.\n";
            $virusTotalData->markFailure( $job );
        } else {
            echo 'Report for job ' . $job->id . " still pending.\n";
        }
    }

    sleep(15);
}
