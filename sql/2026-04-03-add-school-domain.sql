ALTER TABLE schools
  ADD COLUMN domain varchar(255) DEFAULT NULL AFTER code;

ALTER TABLE schools
  ADD UNIQUE KEY uniq_schools_domain (domain);

-- Example data updates:
-- UPDATE schools SET domain = 'funaab.nivasity.com' WHERE code = 'FUNAAB';
-- UPDATE schools SET domain = 'unilag.nivasity.com' WHERE code = 'UNILAG';