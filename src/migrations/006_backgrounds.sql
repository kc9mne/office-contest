-- Page background per contest: 'default' (the mode's built-in image, if it has one), 'none', or 'custom' (uploaded).
ALTER TABLE contests
    ADD COLUMN background_mode VARCHAR(10) NOT NULL DEFAULT 'default',
    ADD COLUMN background_path VARCHAR(190) NULL;
