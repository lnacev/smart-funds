CREATE TABLE `securities` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `ticker`          VARCHAR(20)     NOT NULL,
  `name`            VARCHAR(255)    NOT NULL,
  `type`            ENUM('stock','etf','crypto') NOT NULL,
  `exchange`        VARCHAR(20)     NOT NULL COMMENT 'NYSE, NASDAQ, PSE, CRYPTO',
  `currency`        CHAR(3)         NOT NULL COMMENT 'USD, EUR, CZK',
  `provider`        ENUM('alpha_vantage','coingecko','yahoo') NOT NULL,
  `provider_symbol` VARCHAR(50)     NOT NULL COMMENT 'Přesný symbol pro API',
  `active`          TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticker` (`ticker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `security_prices` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `security_id` INT UNSIGNED    NOT NULL,
  `price`       DECIMAL(15,4)   NOT NULL,
  `currency`    CHAR(3)         NOT NULL,
  `fetched_at`  DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_security` (`security_id`),
  CONSTRAINT `fk_sp_security` FOREIGN KEY (`security_id`) REFERENCES `securities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `portfolio_positions` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `investor_id`       INT UNSIGNED    NOT NULL,
  `security_id`       INT UNSIGNED    NOT NULL,
  `quantity`          DECIMAL(15,6)   NOT NULL,
  `purchase_price`    DECIMAL(15,4)   NOT NULL,
  `purchase_currency` CHAR(3)         NOT NULL,
  `purchased_at`      DATE            NOT NULL,
  `note`              VARCHAR(255)    NULL,
  `created_at`        DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_pp_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  CONSTRAINT `fk_pp_security` FOREIGN KEY (`security_id`) REFERENCES `securities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `watchlist` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `investor_id` INT UNSIGNED    NOT NULL,
  `security_id` INT UNSIGNED    NOT NULL,
  `added_at`    DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_watchlist` (`investor_id`, `security_id`),
  CONSTRAINT `fk_wl_investor` FOREIGN KEY (`investor_id`) REFERENCES `investors` (`id`),
  CONSTRAINT `fk_wl_security` FOREIGN KEY (`security_id`) REFERENCES `securities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exchange_rates` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `from_currency` CHAR(3)         NOT NULL,
  `to_currency`   CHAR(3)         NOT NULL,
  `rate`          DECIMAL(15,6)   NOT NULL,
  `fetched_at`    DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pair` (`from_currency`, `to_currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
