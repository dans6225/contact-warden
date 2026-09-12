CREATE TABLE IF NOT EXISTS cw_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME NOT NULL,
    ip VARCHAR(45) NOT NULL,
    fields JSON NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cw_contacts_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
