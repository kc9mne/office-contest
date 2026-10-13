-- Photo strips keep their individual shots (JSON list of paths). kind is now also 'strip' or 'boomerang'.
ALTER TABLE booth_photos
    ADD COLUMN frames TEXT NULL AFTER result_path;
