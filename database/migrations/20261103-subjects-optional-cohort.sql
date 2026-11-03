-- A subject can now be removed from its cohort and assigned to another one later;
-- NULL means it isn't in a cohort. Existing subjects keep the cohort they're in.
ALTER TABLE `Subjects`
  MODIFY `cohortID` uuid DEFAULT NULL COMMENT 'NULL = not assigned to a cohort';
