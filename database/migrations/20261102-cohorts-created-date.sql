-- Adds the date shown and sorted on by the cohort management page;
-- existing cohorts get the date this migration is run, as their real one wasn't recorded.
ALTER TABLE `Cohorts`
  ADD COLUMN `createdDate` date NOT NULL DEFAULT curdate();
