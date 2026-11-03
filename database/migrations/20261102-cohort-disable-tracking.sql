-- Remembers what disabling a cohort changed, so enabling it only undoes that;
-- students disabled and subjects archived for other reasons stay as they are.
ALTER TABLE `Users`
  ADD COLUMN `disabledByCohort` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = disabled because their cohort was disabled';

ALTER TABLE `Subjects`
  ADD COLUMN `archivedByCohort` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = archived because its cohort was disabled';
