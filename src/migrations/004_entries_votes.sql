-- Contest entries (people enter themselves from the Join page).
-- status: pending | approved | rejected. Pending only happens when the contest requires approval.
CREATE TABLE entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contest_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(80) NOT NULL DEFAULT '',
    title VARCHAR(100) NOT NULL DEFAULT '',
    photo_path VARCHAR(190) NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'approved',
    device_id CHAR(32) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    KEY idx_contest_status (contest_id, status),
    KEY idx_device (contest_id, device_id),
    CONSTRAINT fk_entries_contest FOREIGN KEY (contest_id) REFERENCES contests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One voter per device per contest. The name is what they typed; ip and user_agent help spot duplicates.
CREATE TABLE voters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contest_id INT UNSIGNED NOT NULL,
    device_id CHAR(32) NOT NULL,
    name VARCHAR(80) NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    voided TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_contest_device (contest_id, device_id),
    CONSTRAINT fk_voters_contest FOREIGN KEY (contest_id) REFERENCES contests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One vote per voter per category; changing your pick updates the row.
CREATE TABLE votes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contest_id INT UNSIGNED NOT NULL,
    voter_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    entry_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_voter_category (voter_id, category_id),
    KEY idx_contest (contest_id, category_id, entry_id),
    CONSTRAINT fk_votes_voter FOREIGN KEY (voter_id) REFERENCES voters (id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_entry FOREIGN KEY (entry_id) REFERENCES entries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
