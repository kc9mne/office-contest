-- Photobooth photos can be of one person (portrait) or a group (landscape).
ALTER TABLE booth_photos
    ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'single' AFTER contest_id;
