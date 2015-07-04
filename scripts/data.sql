
CREATE DATABASE IF NOT EXISTS `virustotal`;

USE `virustotal`;

DROP TABLE IF EXISTS `jobs`;

CREATE TABLE `jobs` (
    `id`                BIGINT AUTO_INCREMENT,
    `attachment_id`     VARCHAR(40) DEFAULT NULL UNIQUE,
    `attachment_size`   BIGINT DEFAULT NULL, /* in bytes */
    `attachment_name`   VARCHAR(256) DEFAULT NULL,
    `email_change_key`  VARCHAR(512) DEFAULT NULL,
    `email_id`          VARCHAR(512) DEFAULT NULL,
    `scan_id`           VARCHAR(512) DEFAULT NULL,
    `time_added`        BIGINT DEFAULT NULL,
    `time_sent`         BIGINT DEFAULT NULL,
    `time_completed`    BIGINT DEFAULT NULL,
    `report`            TEXT DEFAULT NULL,
    `status`            VARCHAR(20) DEFAULT 'queued', /* queued, sending, pending, successful, failed */
    INDEX (`email_id`),
    INDEX (`scan_id`),
    INDEX (`status`),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;


