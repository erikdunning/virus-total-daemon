<?php
require 'vendor/autoload.php';

use VirusTotal\Data;
use VirusTotal\Reader;
use VirusTotal\Sender;
use VirusTotal\Responder;

while( true ){

    /* Instantiate Required Classes */
    $virusTotalReader       = new Reader();
    $virusTotalSender       = new Sender();
    $virusTotalResponder    = new Responder();
    $virusTotalData         = new Data();

    /* Download Messages / Attachments and Create Jobs */
    $messages = $virusTotalReader->getMessages();
    foreach( $messages as $msg ){
        $attachmentItems = $virusTotalReader->downloadAttachments( $msg );
        $virusTotalData->createJobs( $msg, $attachmentItems );
    }

    print_r($messages);

    /* Perform Sender Operation */
    $job = $virusTotalData->getQueued();
    if( $job ){
        $virusTotalData->markSending( $job );
        $virusTotalSender->sendVirusTotalRequest( $job );
        $virusTotalData->markPending( $job );
    }

    /* Perform Responder Operation */
    $job = $virusTotalData->getPending();
    if( $job ){
        $report = $virusTotalSender->getVirusTotalReport( $job ); 
        if( $report ){
            $virusTotalData->setReportData( $job, $report );
            $virusTotalResponder->sendReply( $job, $report );
        }
    }

    sleep(30);
}
