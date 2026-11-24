-- MFA backup codes, up to 8 one-use codes per account for logging in when the security code email
-- doesn't arrive. Only password_hash() hashes are stored, not the codes.
-- Deleting someone's rows here makes the site show them a new set at their next login.
CREATE TABLE `MfaBackupCodes` (
  `backupCodeID` uuid NOT NULL DEFAULT uuid(),
  `userID` uuid NOT NULL,
  `codeHash` varchar(255) NOT NULL COMMENT 'password_hash of a backup code, never the code itself',
  PRIMARY KEY (`backupCodeID`),
  KEY `idx_mfa_backup_codes_user` (`userID`),
  CONSTRAINT `fk_mfa_backup_codes_user` FOREIGN KEY (`userID`)
    REFERENCES `Users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
