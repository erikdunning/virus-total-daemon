<?php

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/logs/daemon-error.log");
error_reporting(E_ALL);

while( true ){

    /* Instantiate Required Classes and Objects */
    $startDate              = isset( $startDate ) ? $startDate : (new DateTime())->setTimestamp(time() - ( 24 * 60 * 60 ));
    $lastBegan              = (new DateTime())->setTimestamp(time() - 60);
    $config                 = json_decode( file_get_contents( __DIR__ . '/config.json') );

    /* Download Messages / Attachments and Create Jobs */
    $task = '/usr/bin/php ' . __DIR__ . '/scripts/reader_daemon.php ' . escapeshellarg( $startDate->format('c') )  . ' &';
    `$task`;

    $startDate = $lastBegan;

    /* Perform Sender Operation */
    $task = '/usr/bin/php ' . __DIR__ . '/scripts/sender_daemon.php &';
    `$task`;

    sleep(15);

    /* Perform Responder Operation */
    $task = '/usr/bin/php ' . __DIR__ . '/scripts/responder_daemon.php &';
    `$task`;

    sleep(15);

}
