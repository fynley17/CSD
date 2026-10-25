-- Existing accounts keep working because the new column defaults to verified;
-- registration sets it to 0 until the student clicks the link in their email.
ALTER TABLE `Users`
  ADD COLUMN `isEmailVerified` tinyint(1) NOT NULL DEFAULT 1
    COMMENT '0 = registered but the email link not clicked yet' AFTER `isEnabled`;

CREATE TABLE `EmailVerifications` (
  `userID` uuid NOT NULL,
  `tokenHash` char(64) NOT NULL COMMENT 'SHA-256 hash of the emailed link code, never the code itself',
  `expiresAt` datetime NOT NULL,
  `sentAt` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Used to stop repeated resend requests',
  PRIMARY KEY (`userID`),
  UNIQUE KEY `uq_email_verifications_token` (`tokenHash`),
  CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`userID`)
    REFERENCES `Users` (`userID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
