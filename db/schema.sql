CREATE DATABASE IF NOT EXISTS `arca_gateway` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `arca_gateway`;

CREATE TABLE IF NOT EXISTS `orders` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `status`     TINYINT          NOT NULL DEFAULT 0 COMMENT '0=pending 1=registered 2=authorized 3=captured 4=expired',
  `orderid`    VARCHAR(64)      NOT NULL,
  `clientid`   VARCHAR(64)      NOT NULL DEFAULT '',
  `email`      VARCHAR(255)     NOT NULL DEFAULT '',
  `phone`      VARCHAR(64)      NOT NULL DEFAULT '',
  `created`    DATETIME         NOT NULL,
  `price`      DECIMAL(12,2)    NOT NULL DEFAULT 0,
  `currency`   VARCHAR(8)       NOT NULL DEFAULT 'AMD',
  `status_url` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orderid` (`orderid`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transaction` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `status`       TINYINT       NOT NULL DEFAULT 0 COMMENT '0=open 1=closed 2=canceled',
  `orderid`      VARCHAR(64)   NOT NULL,
  `externaltrid` VARCHAR(64)   NOT NULL DEFAULT '',
  `redirect`     TEXT,
  `price`        DECIMAL(12,2) NOT NULL DEFAULT 0,
  `trstatus`     VARCHAR(32)   NOT NULL DEFAULT 'pending' COMMENT 'pending|authorized|captured|declined|canceled|deleted',
  `gateway`      VARCHAR(64)   NOT NULL DEFAULT 'ArCa',
  `program`      TINYINT       NOT NULL DEFAULT 0 COMMENT '0=init 1=registered 2=authorized 3=captured 4=voided',
  `lang`         VARCHAR(4)    NOT NULL DEFAULT 'en',
  `created`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orderid`      (`orderid`),
  KEY `idx_externaltrid` (`externaltrid`),
  KEY `idx_trstatus`     (`trstatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transaction_details` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `externaltrid`   VARCHAR(64)  NOT NULL,
  `pan`            VARCHAR(32)  NOT NULL DEFAULT '',
  `cardholderName` VARCHAR(128) NOT NULL DEFAULT '',
  `approvalCode`   VARCHAR(32)  NOT NULL DEFAULT '',
  `cardBrand`      VARCHAR(32)  NOT NULL DEFAULT '',
  `bankName`       VARCHAR(128) NOT NULL DEFAULT '',
  `ip`             VARCHAR(45)  NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_externaltrid` (`externaltrid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `process` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `externaltrid` VARCHAR(64)   NOT NULL,
  `shopify_id`   VARCHAR(64)   NOT NULL,
  `amount`       INT           NOT NULL DEFAULT 0 COMMENT 'In kopeks',
  `retry`        TINYINT       NOT NULL DEFAULT 0,
  `created`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_externaltrid` (`externaltrid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
