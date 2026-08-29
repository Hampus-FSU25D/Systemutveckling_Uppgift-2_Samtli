CREATE TABLE group_invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    used_by BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_group_invitations_token_hash UNIQUE (token_hash),
    INDEX idx_group_invitations_group (group_id, created_at),
    INDEX idx_group_invitations_created_by (created_by),
    INDEX idx_group_invitations_used_by (used_by),
    INDEX idx_group_invitations_expires_unused (used_at, expires_at),
    CONSTRAINT fk_group_invitations_group
        FOREIGN KEY (group_id) REFERENCES groups (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_group_invitations_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_group_invitations_used_by
        FOREIGN KEY (used_by) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
