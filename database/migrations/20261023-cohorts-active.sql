-- Brings databases made before the cohorts update in line with 01-schema.sql;
-- existing cohorts stay active and their names are unchanged.
ALTER TABLE `Cohorts`
  MODIFY `cohortName` varchar(50) NOT NULL,
  ADD COLUMN `isActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = disabled';
