-- Add archive support to Tests.
-- Existing tests remain active by default.
ALTER TABLE Tests
ADD COLUMN isActive TINYINT(1) NOT NULL DEFAULT 1;
