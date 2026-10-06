-- Site-wide settings: company name, logo, brand color, time zone, admin PIN and link.
CREATE TABLE settings (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per contest. Times are stored in UTC.
-- status: draft | active | archived. Only one contest is active (shown on the home page) at a time.
CREATE TABLE contests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mode VARCHAR(20) NOT NULL,
    title VARCHAR(120) NOT NULL,
    subtitle VARCHAR(120) NOT NULL DEFAULT '',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'draft',
    event_name VARCHAR(80) NOT NULL DEFAULT '',
    event_details VARCHAR(160) NOT NULL DEFAULT '',
    require_approval TINYINT(1) NOT NULL DEFAULT 0,
    show_counts TINYINT(1) NOT NULL DEFAULT 1,
    booth_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contest_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_contest_name (contest_id, name),
    CONSTRAINT fk_categories_contest FOREIGN KEY (contest_id) REFERENCES contests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed admin PIN attempts, for lockout after too many tries.
CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
