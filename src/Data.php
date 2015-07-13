<?php

namespace VirusTotal;

use PDO;

class Data {

    protected $dbh;

    public function __construct(){
        $c = json_decode( file_get_contents( __DIR__ . '/../config.json' )  ); 
        $dsn = 'mysql:dbname='.$c->mysql->database.';host='.$c->mysql->host;
        try {
            $this->dbh = new PDO($dsn, $c->mysql->username, $c->mysql->password);
        } catch (PDOException $e) {
            error_log("Virus Total Daemon: Connection failed. \n" . $e->getMessage());
        }
    }

    public function attachmentExists( $attachmentId ){
        $st = $this->dbh->prepare('SELECT `id` FROM `jobs` WHERE `attachment_id` = ? LIMIT 1');
        $st->execute(array( $attachmentId ));
        $result = $st->fetchAll(PDO::FETCH_OBJ);
        if( sizeof( $result ) > 0 ){
            return true;
        }
        return false;
    }

    public function createJobs( $message, $attachmentItems ){

        $stmt = $this->dbh->prepare("
            INSERT INTO `jobs` ( 
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

            if( $this->attachmentExists( $attachmentId ) ){
                continue;
            }

            $stmt->execute();
        }
        
    }

    public function getQueued(){
        $stmt = $this->dbh->query('SELECT * FROM `jobs` WHERE `status` = \'queued\' ORDER BY `time_added` ASC LIMIT 10');
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_OBJ);
        if( sizeof( $result ) > 0 ){
            return $result[array_rand($result)];
        }
        return false;
    }

    public function markSending( $job ){
        $timeSent = gmdate('U');
        $stmt = $this->dbh->query("UPDATE `jobs` SET `status` = 'sending', `time_sent` = $timeSent WHERE `id` = $job->id");
        return $stmt->execute();
    }

    public function markPending( $job, $scanId ){
        $timeSent = gmdate('U');
        $stmt = $this->dbh->prepare("
            UPDATE `jobs` SET
                `scan_id` = ?,
                `status` = 'pending',
                `time_sent` = ?
            WHERE
                `id` = ?
        ");
        return $stmt->execute(array( $scanId, $timeSent, $job->id ));
    }

    public function getPending(){
        $stmt = $this->dbh->query('SELECT * FROM `jobs` WHERE `status` = \'pending\' ORDER BY `time_sent` ASC LIMIT 10');
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_OBJ);
        if( sizeof( $result ) > 0 ){
            return $result[array_rand($result)];
        }
        return false;
    }

    public function setReportData( $job, $report ){
        $stmt = $this->dbh->prepare("
            UPDATE `jobs` SET
                `report` = ?
            WHERE
                `id` = ?
        ");
        return $stmt->execute( array( $report, $job->id ) );
    }

    public function markSuccess( $job ){
        $timeCompleted = gmdate('U');
        $stmt = $this->dbh->query("UPDATE `jobs` SET `status` = 'success', `time_completed` = $timeCompleted WHERE `id` = $job->id");
        return $stmt->execute();
    }

    public function markFailure( $job ){
        $timeCompleted = gmdate('U');
        $stmt = $this->dbh->query("UPDATE `jobs` SET `status` = 'failure', `time_completed` = $timeCompleted WHERE `id` = $job->id");
        return $stmt->execute();
    }

    public function getCompletedSet( $job ){
        $stmt = $this->dbh->prepare("SELECT * FROM `jobs` WHERE `email_id` = ? AND `email_change_key` = ? ORDER BY `time_added` ASC");
        $stmt->execute(array( $job->email_id, $job->email_change_key ));
        $result = $stmt->fetchAll(PDO::FETCH_OBJ);
        $completed = false;
        if( sizeof( $result ) > 0 ){
            $completed = array();
            foreach( $result as $j ){
                if( ( $j->status === 'success' ) || ( $j->status === 'failure' ) ){
                    $completed[] = $j;
                }
            }
            if( sizeof( $result ) !== sizeof( $completed ) ){
                $completed = false;
            }
        }
        return $completed;
    }

}


