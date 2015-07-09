<?php

namespace VirusTotal;

class Sender {

    protected $config;

    public function __construct(){
        $this->config = json_decode( file_get_contents( __DIR__ . '/../config.json' )  );
    }

    public function sendVirusTotalRequest( $job ){
        $curl = 'curl -X POST -s ' . $this->config->virusTotal->apiScan;
        $curl .= ' -F apikey=' . $this->config->virusTotal->apiKey;
        $curl .= ' -F file=@' . $this->config->attachments->directory . '/' . $job->attachment_id;
        $raw = `$curl`;
        $result = json_decode( $raw );
        if( $result && is_object( $result ) && property_exists($result, 'scan_id') ){
            unlink( $this->config->attachments->directory . '/' . $job->attachment_id );
            return $result->scan_id;  
        } else {
            error_log("Virus Total Daemon: Error sending file.\n" . print_r( $result, true ));
        }
        return false;
    }

    public function getVirusTotalReport( $job ){
        $curl = 'curl -X POST -s ' . $this->config->virusTotal->apiReport;
        $curl .= ' -F apikey=' . $this->config->virusTotal->apiKey;
        $curl .= ' -F resource=' . $job->scan_id;
        $raw = `$curl`;
        $result = json_decode( $raw ); 
        if( $result && is_object( $result ) && property_exists($result, 'response_code') && ( $result->response_code == 1 ) ){
            return $raw;
        } else if( $result && is_object( $result ) && property_exists($result, 'response_code') && ( $result->response_code == 0 ) ){
            return 0;
        } else if( ( ! $result ) || ( ! is_object( $result ) ) ) {
            error_log("Virus Total Daemon: Error retrieving report for job $job->id.\n" . print_r( $result, true ));
            return 0;
        }
        return -2;
    }
}
