<?php

use App\Models\Model;
use App\Models\PasswordReset;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the forgot password links in PasswordReset
 * each test is laid out Arrange, Act, Assert
 */
final class PasswordResetTest extends TestCase
{
    private const NEW_PASSWORD = 'NewPass1!';

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA foreign_keys = ON');

        $this->db->exec("
            CREATE TABLE Users (
                userID TEXT PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                accountType TEXT NOT NULL,
                approvalStatus TEXT NOT NULL DEFAULT 'approved',
                isEnabled INTEGER NOT NULL DEFAULT 1,
                isEmailVerified INTEGER NOT NULL DEFAULT 1,
                failedAttempts INTEGER NOT NULL DEFAULT 0,
                lockedUntil TEXT NULL
            );

            CREATE TABLE PasswordResetTokens (
                userID TEXT PRIMARY KEY,
                tokenHash TEXT NOT NULL UNIQUE,
                expiresAt TEXT NOT NULL,
                sentAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (userID) REFERENCES Users(userID)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            );
        ");

        $oldHash = password_hash('OldPass1!', PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO Users (userID, username, email, password, accountType, isEnabled, isEmailVerified)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(['user-1', 'ws000001', 'student@example.com', $oldHash, 'student', 1, 1]);
        $stmt->execute(['user-2', 'ws000002', 'other@example.com', $oldHash, 'student', 1, 1]);
        $stmt->execute(['disabled', 'ws000003', 'disabled@example.com', $oldHash, 'student', 0, 1]);
        $stmt->execute(['unverified', 'ws000004', 'unverified@example.com', $oldHash, 'student', 1, 0]);
        $stmt->execute(['lecturer', 'L1001', 'lecturer@example.com', $oldHash, 'lecturer', 1, 1]);

        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, null);
    }

    /**
     * A verified, enabled account gets a link, and only the hash of its code is saved (normal)
     */
    public function testMakesALinkAndOnlySavesItsHash(): void
    {
        // Arrange: setUp() has made the accounts

        // Act
        $reset = PasswordReset::createForEmail('student@example.com');

        // Assert
        self::assertNotNull($reset);
        self::assertSame('student@example.com', $reset['email']);
        self::assertTrue(PasswordReset::isValidToken($reset['token']));

        $saved = $this->db->query("SELECT tokenHash FROM PasswordResetTokens WHERE userID = 'user-1'")->fetchColumn();
        self::assertSame(PasswordReset::hashToken($reset['token']), $saved);
        self::assertNotSame($reset['token'], $saved);
    }

    /**
     * Case and spaces don't matter, the same way emails are saved (boundary)
     */
    public function testFindsTheAccountWhateverTheCase(): void
    {
        // Act
        $reset = PasswordReset::createForEmail('  Student@Example.COM ');

        // Assert
        self::assertNotNull($reset);
        self::assertSame('student@example.com', $reset['email']);
    }

    /**
     * Staff accounts can reset their password too (normal)
     */
    public function testLecturersCanAskForALink(): void
    {
        // Act
        $reset = PasswordReset::createForEmail('lecturer@example.com');

        // Assert
        self::assertNotNull($reset);
    }

    /**
     * No link for an unknown, disabled or unverified account (error)
     */
    public function testNoLinkForAccountsThatCannotReset(): void
    {
        // Act
        $unknown = PasswordReset::createForEmail('nobody@example.com');
        $disabled = PasswordReset::createForEmail('disabled@example.com');
        $unverified = PasswordReset::createForEmail('unverified@example.com');

        // Assert
        self::assertNull($unknown);
        self::assertNull($disabled);
        self::assertNull($unverified);
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM PasswordResetTokens')->fetchColumn());
    }

    /**
     * A second request within a minute sends nothing (boundary)
     */
    public function testWaitsAMinuteBeforeAnotherLink(): void
    {
        // Act
        $first = PasswordReset::createForEmail('student@example.com');
        $second = PasswordReset::createForEmail('student@example.com');

        // Assert
        self::assertNotNull($first);
        self::assertNull($second);
    }

    /**
     * A new link replaces the old one, so only the newest email works (normal)
     */
    public function testANewLinkStopsTheOldOneWorking(): void
    {
        // Arrange
        $old = PasswordReset::createForEmail('student@example.com');
        $this->db->exec("UPDATE PasswordResetTokens SET sentAt = datetime('now', '-2 minutes')");
        $new = PasswordReset::createForEmail('student@example.com');

        // Act
        $oldResult = PasswordReset::resetPassword($old['token'], self::NEW_PASSWORD);
        $newResult = PasswordReset::resetPassword($new['token'], self::NEW_PASSWORD);

        // Assert
        self::assertNull($oldResult);
        self::assertSame('student@example.com', $newResult);
    }

    /**
     * The link changes the password of its own account and nothing else (normal)
     */
    public function testOnlyChangesThePasswordOfTheLinksAccount(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Users SET failedAttempts = 2, lockedUntil = '2099-01-01 00:00:00' WHERE userID = 'user-1'"
        );
        $before = $this->allUsers();
        $reset = PasswordReset::createForEmail('student@example.com');

        // Act
        $email = PasswordReset::resetPassword($reset['token'], self::NEW_PASSWORD);

        // Assert
        self::assertSame('student@example.com', $email);
        $after = $this->allUsers();

        // the new password works and is stored as a hash
        self::assertTrue(password_verify(self::NEW_PASSWORD, $after['user-1']['password']));
        self::assertNotSame(self::NEW_PASSWORD, $after['user-1']['password']);

        // every other column of the account is the same
        unset($before['user-1']['password'], $after['user-1']['password']);
        self::assertSame($before['user-1'], $after['user-1']);

        // every other account is untouched
        unset($before['user-1'], $after['user-1']);
        self::assertSame($before, $after);
    }

    /**
     * A link can only be used once (error)
     */
    public function testALinkCannotBeUsedTwice(): void
    {
        // Arrange
        $reset = PasswordReset::createForEmail('student@example.com');
        PasswordReset::resetPassword($reset['token'], self::NEW_PASSWORD);

        // Act
        $secondUse = PasswordReset::resetPassword($reset['token'], 'Another1!');

        // Assert
        self::assertNull($secondUse);
        self::assertTrue(password_verify(self::NEW_PASSWORD, $this->passwordOf('user-1')));
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM PasswordResetTokens')->fetchColumn());
    }

    /**
     * An expired link changes nothing (boundary)
     */
    public function testAnExpiredLinkDoesNothing(): void
    {
        // Arrange
        $reset = PasswordReset::createForEmail('student@example.com');
        $this->db->exec("UPDATE PasswordResetTokens SET expiresAt = datetime('now', '-1 second')");

        // Act
        $result = PasswordReset::resetPassword($reset['token'], self::NEW_PASSWORD);

        // Assert
        self::assertNull($result);
        self::assertTrue(password_verify('OldPass1!', $this->passwordOf('user-1')));
    }

    /**
     * An account disabled after the email was sent can't use its link (error)
     */
    public function testADisabledAccountCannotUseItsLink(): void
    {
        // Arrange
        $reset = PasswordReset::createForEmail('student@example.com');
        $this->db->exec("UPDATE Users SET isEnabled = 0 WHERE userID = 'user-1'");

        // Act
        $result = PasswordReset::resetPassword($reset['token'], self::NEW_PASSWORD);

        // Assert
        self::assertNull($result);
        self::assertTrue(password_verify('OldPass1!', $this->passwordOf('user-1')));
    }

    /**
     * A weak password is turned down and the link still works afterwards (error)
     */
    public function testAWeakPasswordIsTurnedDownAndTheLinkKept(): void
    {
        // Arrange
        $reset = PasswordReset::createForEmail('student@example.com');

        // Act
        try {
            PasswordReset::resetPassword($reset['token'], 'weak');
            self::fail('A weak password should be turned down');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
        }
        $retry = PasswordReset::resetPassword($reset['token'], self::NEW_PASSWORD);

        // Assert
        self::assertStringContainsString('at least 8 characters', $message);
        self::assertSame('student@example.com', $retry);
    }

    /**
     * Made up or wrongly shaped codes change nothing (error)
     */
    public function testMadeUpCodesDoNothing(): void
    {
        // Arrange
        PasswordReset::createForEmail('student@example.com');

        // Act
        $madeUp = PasswordReset::resetPassword(PasswordReset::makeToken(), self::NEW_PASSWORD);
        $wrongShape = PasswordReset::resetPassword("' OR 1=1 --", self::NEW_PASSWORD);

        // Assert
        self::assertNull($madeUp);
        self::assertNull($wrongShape);
        self::assertTrue(password_verify('OldPass1!', $this->passwordOf('user-1')));
    }

    /** @return array<string, array<string, mixed>> every account, keyed by userID */
    private function allUsers(): array
    {
        $users = [];
        foreach ($this->db->query('SELECT * FROM Users ORDER BY userID')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $users[$row['userID']] = $row;
        }

        return $users;
    }

    private function passwordOf(string $userID): string
    {
        $stmt = $this->db->prepare('SELECT password FROM Users WHERE userID = ?');
        $stmt->execute([$userID]);

        return (string) $stmt->fetchColumn();
    }
}
