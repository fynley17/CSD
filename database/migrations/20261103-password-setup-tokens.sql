CREATE TABLE `PasswordSetupTokens` (
  `userID` uuid NOT NULL,
  `tokenHash` char(64) NOT NULL COMMENT 'SHA-256 hash of the emailed password setup code, never the code itself',
  `expiresAt` datetime NOT NULL,
  `sentAt` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Used to stop repeated resend requests',
  PRIMARY KEY (`userID`),
  UNIQUE KEY `uq_password_setup_tokens_token` (`tokenHash`),
  CONSTRAINT `fk_password_setup_tokens_user` FOREIGN KEY (`userID`)
    REFERENCES `Users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
