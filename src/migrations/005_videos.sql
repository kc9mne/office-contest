-- Who can add videos: anyone | admins
ALTER TABLE contests
    ADD COLUMN video_posting VARCHAR(10) NOT NULL DEFAULT 'anyone';

-- Parade / walk / tasting videos: uploaded files (converted to MP4 in the background) or YouTube links.
-- status: processing | ready | failed. visible = 0 when hidden by an admin or waiting for approval.
CREATE TABLE videos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contest_id INT UNSIGNED NOT NULL,
    kind VARCHAR(10) NOT NULL,
    title VARCHAR(120) NOT NULL DEFAULT '',
    posted_by VARCHAR(80) NOT NULL DEFAULT '',
    youtube_id VARCHAR(20) NULL,
    original_path VARCHAR(190) NULL,
    video_path VARCHAR(190) NULL,
    poster_path VARCHAR(190) NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    duration_sec INT UNSIGNED NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'ready',
    error VARCHAR(255) NOT NULL DEFAULT '',
    visible TINYINT(1) NOT NULL DEFAULT 1,
    device_id CHAR(32) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    KEY idx_contest (contest_id, status, visible),
    CONSTRAINT fk_videos_contest FOREIGN KEY (contest_id) REFERENCES contests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
