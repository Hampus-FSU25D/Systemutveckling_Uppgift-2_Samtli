CREATE TABLE group_join_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    handled_at TIMESTAMP NULL DEFAULT NULL,
    handled_by BIGINT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_group_join_requests_group_user UNIQUE (group_id, user_id),
    INDEX idx_group_join_requests_group_status (group_id, status, created_at),
    INDEX idx_group_join_requests_user (user_id),
    INDEX idx_group_join_requests_handled_by (handled_by),
    CONSTRAINT chk_group_join_requests_status CHECK (status IN ('pending', 'approved', 'rejected')),
    CONSTRAINT fk_group_join_requests_group
        FOREIGN KEY (group_id) REFERENCES groups (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_group_join_requests_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_group_join_requests_handled_by
        FOREIGN KEY (handled_by) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
