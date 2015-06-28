<?php

namespace VirusTotal;

use PDO;

class Data {

    protected $dbh;

    public function __construct( $host, $database, $username, $password ){
        $dsn = "mysql:dbname=$database;host=$host";
        try {
            $this->dbh = new PDO($dsn, $username, $password);
        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
        }
    }

    public function createJobs( $message, $attachmentItems ){

        $stmt = $this->dbh->prepare("
            INSERT INTO `virustotal`.`jobs` ( 
                email_id,
                email_change_key,
                attachment_id, 
                attachment_size,
                attachment_name,
                time_added,
                status
            ) VALUES (
                :email_id,
                :email_change_key,
                :attachment_id, 
                :attachment_size,
                :attachment_name,
                :time_added,
                :status
            )
        ");

        $stmt->bindParam(':email_id', $emailId);
        $stmt->bindParam(':email_change_key', $emailChangeKey);
        $stmt->bindParam(':attachment_id', $attachmentId);
        $stmt->bindParam(':attachment_size', $attachmentSize);
        $stmt->bindParam(':attachment_name', $attachmentName);
        $stmt->bindParam(':time_added', $timeAdded);
        $stmt->bindParam(':status', $status);

        foreach( $attachmentItems as $attachment ){
            $emailId = $message->ItemId->Id;
            $emailChangeKey = $message->ItemId->ChangeKey;
            $attachmentId = $attachment['attachment_id'];
            $attachmentSize = $attachment['attachment_size'];
            $attachmentName = $attachment['attachment_name'];
            $timeAdded = gmdate('U');
            $status = 'queued';

            $stmt->execute();
        }
        
    }

}


