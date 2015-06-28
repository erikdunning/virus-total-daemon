<?php
require 'vendor/autoload.php';

use VirusTotal\Reader;
use VirusTotal\Data;

    /* Load Configuration File */
    $c = json_decode( file_get_contents( __DIR__ . '/config.json' )  ); 

     
    /* Instantiate Required Classes */
    $virusTotalReader = new Reader( $c->exchange->host, $c->exchange->username, $c->exchange->password );
    $virusTotalData = new Data( $c->mysql->host, $c->mysql->database, $c->mysql->username, $c->mysql->password );

    $messages = $virusTotalReader->getMessages();
    foreach( $messages as $msg ){
        // download attachments

        $attachmentItems = $virusTotalReader->downloadAttachments( $msg->ItemId->Id, __DIR__ . '/attachments' );
        // if exists in db return

        // create db entry
        $virusTotalData->createJobs( $msg, $attachmentItems );


    }

    print_r($messages);
