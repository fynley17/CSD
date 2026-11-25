-- User profile: a new email address typed on the profile page waits here until its link is used,
-- so the account keeps its old address (and can still log in and get emails) until the new one is confirmed.
-- NULL = the link verifies the address already on the account, i.e. after registering.
ALTER TABLE `EmailVerifications`
  ADD COLUMN `newEmail` varchar(255) DEFAULT NULL
    COMMENT 'Address that replaces Users.email once this link is used, NULL = verifying the current address'
    AFTER `sentAt`;
