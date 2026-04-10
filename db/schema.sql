CREATE TABLE funds (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE investors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL,
    UNIQUE KEY uq_investors_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transactions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fund_id     INT UNSIGNED   NOT NULL,
    investor_id INT UNSIGNED   NOT NULL,
    amount      DECIMAL(15, 4) NOT NULL,
    created_at  DATETIME       NOT NULL,
    CONSTRAINT fk_transactions_fund     FOREIGN KEY (fund_id)     REFERENCES funds(id),
    CONSTRAINT fk_transactions_investor FOREIGN KEY (investor_id) REFERENCES investors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
