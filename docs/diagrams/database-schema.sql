-- Comments SPA schema for MySQL Workbench Reverse Engineer.
-- Source of truth: database/migrations/*.php. The media-to-comments link is polymorphic
-- and intentionally has no physical foreign key.

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    home_page TEXT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(6) NULL,
    updated_at TIMESTAMP(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email),
    KEY users_name_id_idx (name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    parent_id BIGINT UNSIGNED NULL,
    root_id BIGINT UNSIGNED NULL,
    guest_name VARCHAR(255) NULL,
    guest_email VARCHAR(255) NULL,
    guest_home_page TEXT NULL,
    body_html MEDIUMTEXT NOT NULL,
    edited_at TIMESTAMP(6) NULL,
    deletion_type VARCHAR(32) NULL,
    deleted_at TIMESTAMP(6) NULL,
    created_at TIMESTAMP(6) NULL,
    updated_at TIMESTAMP(6) NULL,
    PRIMARY KEY (id),
    KEY comments_root_created_idx (parent_id, created_at, id),
    KEY comments_tree_idx (root_id, parent_id, created_at, id),
    KEY comments_user_root_idx (user_id, parent_id, id),
    KEY comments_guest_name_idx (parent_id, user_id, guest_name, id),
    KEY comments_guest_email_idx (parent_id, user_id, guest_email, id),
    CONSTRAINT comments_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT comments_parent_id_foreign FOREIGN KEY (parent_id)
        REFERENCES comments (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT comments_root_id_foreign FOREIGN KEY (root_id)
        REFERENCES comments (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT comments_author_invariant_chk CHECK (
        (user_id IS NOT NULL AND guest_name IS NULL AND guest_email IS NULL AND guest_home_page IS NULL)
        OR (user_id IS NULL AND guest_name IS NOT NULL AND guest_email IS NOT NULL)
    ),
    CONSTRAINT comments_parent_root_pair_chk CHECK (
        (parent_id IS NULL AND root_id IS NULL)
        OR (parent_id IS NOT NULL AND root_id IS NOT NULL)
    ),
    CONSTRAINT comments_deletion_type_chk CHECK (
        deletion_type IS NULL OR deletion_type IN ('user', 'moderator', 'administrator', 'system')
    ),
    CONSTRAINT comments_deletion_state_chk CHECK (
        (deleted_at IS NULL AND deletion_type IS NULL)
        OR (deleted_at IS NOT NULL AND deletion_type IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NULL,
    collection_name VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NULL,
    disk VARCHAR(255) NOT NULL,
    conversions_disk VARCHAR(255) NULL,
    size BIGINT UNSIGNED NOT NULL,
    manipulations JSON NOT NULL,
    custom_properties JSON NOT NULL,
    generated_conversions JSON NOT NULL,
    responsive_images JSON NOT NULL,
    order_column INT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY media_uuid_unique (uuid),
    KEY media_model_type_model_id_index (model_type, model_id),
    KEY media_order_column_index (order_column)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(255) NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY failed_jobs_uuid_unique (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
