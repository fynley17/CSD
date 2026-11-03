-- Stops two cohorts having the same name, the check ignores upper and lower case.
-- This fails if duplicates already exist, rename them first.
ALTER TABLE `Cohorts`
  ADD UNIQUE KEY `uq_cohorts_name` (`cohortName`);
