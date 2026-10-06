-- Photobooth: per-contest looks (JSON list of {name, prompt, from, to}) and a daily AI photo limit.
ALTER TABLE contests
    ADD COLUMN booth_styles TEXT NULL,
    ADD COLUMN booth_daily_limit INT UNSIGNED NOT NULL DEFAULT 200;

-- One row per booth photo. ai_runs counts AI calls (a "try another look" is another run).
-- status: pending | processing | done | failed
CREATE TABLE booth_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(16) NOT NULL,
    contest_id INT UNSIGNED NOT NULL,
    style VARCHAR(60) NOT NULL DEFAULT '',
    name VARCHAR(80) NOT NULL DEFAULT '',
    original_path VARCHAR(190) NOT NULL,
    result_path VARCHAR(190) NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'pending',
    error VARCHAR(255) NOT NULL DEFAULT '',
    ai_runs INT UNSIGNED NOT NULL DEFAULT 0,
    in_gallery TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    UNIQUE KEY uq_code (code),
    KEY idx_contest_time (contest_id, created_at),
    CONSTRAINT fk_booth_contest FOREIGN KEY (contest_id) REFERENCES contests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
