CREATE TABLE IF NOT EXISTS cw_tokens (
    id VARCHAR(128) NOT NULL,
    field_map JSON NOT NULL,
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    ip VARCHAR(45) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cw_tokens_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cw_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME NOT NULL,
    ip VARCHAR(45) NOT NULL,
    decision ENUM('ACCEPT', 'CHALLENGE', 'REJECT') NOT NULL,
    score INT NOT NULL,
    meta JSON NULL,
    PRIMARY KEY (id),
    KEY idx_cw_submissions_ip (ip),
    KEY idx_cw_submissions_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cw_abuse_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME NOT NULL,
    ip VARCHAR(45) NOT NULL,
    score INT NOT NULL,
    decision ENUM('CHALLENGE', 'REJECT') NOT NULL,
    evidence JSON NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cw_abuse_log_ip (ip),
    KEY idx_cw_abuse_log_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cw_reputation (
    subject VARCHAR(64) NOT NULL COMMENT 'IP address or netblock (CIDR)',
    score FLOAT NOT NULL DEFAULT 0,
    last_updated DATETIME NOT NULL,
    PRIMARY KEY (subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
