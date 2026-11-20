-- Forgot password links, kept apart from PasswordSetupTokens so a setup link can't reset a password
-- and a reset link can't set up a new account.
CREATE TABLE `PasswordResetTokens` (
  `userID` uuid NOT NULL,
  `tokenHash` char(64) NOT NULL COMMENT 'SHA-256 hash of the emailed reset code, never the code itself',
  `expiresAt` datetime NOT NULL,
  `sentAt` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Used to stop repeated reset requests',
  PRIMARY KEY (`userID`),
  UNIQUE KEY `uq_password_reset_tokens_token` (`tokenHash`),
  CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`userID`)
    REFERENCES `Users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
