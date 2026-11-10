<?php

use App\Models\Model;
use App\Models\PasswordSetup;
use PHPUnit\Framework\TestCase;

final class PasswordSetupTest extends TestCase
{
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
                approvalStatus TEXT NOT NULL,
                isEnabled INTEGER NOT NULL DEFAULT 1,
                isEmailVerified INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE PasswordSetupTokens (
                userID TEXT PRIMARY KEY,
                tokenHash TEXT NOT NULL UNIQUE,
                expiresAt TEXT NOT NULL,
                sentAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (userID) REFERENCES Users(userID)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            );
        ");

        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionProperty(Model::class, 'db');
        $reflection->setValue(null, null);
    }

    private function createUser(
        string $userID = 'student-1',
        string $accountType = 'student',
        int $isEnabled = 1,
        int $isEmailVerified = 0
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO Users (
                userID,
                username,
                email,
                password,
                accountType,
                approvalStatus,
                isEnabled,
                isEmailVerified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userID,
            $userID,
            $userID . '@example.com',
            'unusable-password-hash',
            $accountType,
            'approved',
            $isEnabled,
            $isEmailVerified,
        ]);
    }

    public function testGeneratesSecureTokenAndStoresOnlyItsHash(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        $this->assertSame(64, strlen($token));
        $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/D', $token));

        $stmt = $this->db->prepare(
            'SELECT tokenHash FROM PasswordSetupTokens WHERE userID = ?'
        );
        $stmt->execute(['student-1']);

        $storedHash = $stmt->fetchColumn();

        $this->assertSame(hash('sha256', $token), $storedHash);
        $this->assertNotSame($token, $storedHash);
    }

    public function testCreatesTokenForUnverifiedStudentOnly(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        $this->assertNotEmpty($token);

        $this->createUser(
            userID: 'verified-student',
            isEmailVerified: 1
        );

        $this->expectException(InvalidArgumentException::class);

        PasswordSetup::createToken('verified-student');
    }

    public function testRefusesALinkForADisabledAccount(): void
    {
        // Arrange
        $this->createUser(isEnabled: 0);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        PasswordSetup::createToken('student-1');
    }

    public function testFindsValidToken(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        $result = PasswordSetup::findByToken($token);

        $this->assertIsArray($result);
        $this->assertSame('student-1', $result['userID']);
        $this->assertFalse($result['isExpired']);
    }

    public function testReturnsNullForInvalidToken(): void
    {
        $this->createUser();

        $result = PasswordSetup::findByToken('invalid-token');

        $this->assertNull($result);
    }

    public function testReturnsNullForUnknownToken(): void
    {
        $result = PasswordSetup::findByToken(str_repeat('a', 64));

        $this->assertNull($result);
    }

    public function testExpiredTokenIsMarkedAsExpired(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        $this->db->exec("
            UPDATE PasswordSetupTokens
            SET expiresAt = datetime('now', '-1 hour')
            WHERE userID = 'student-1'
        ");

        $result = PasswordSetup::findByToken($token);

        $this->assertIsArray($result);
        $this->assertTrue($result['isExpired']);
    }

    public function testCanResendWhenNoTokenExists(): void
    {
        $this->createUser();

        $this->assertTrue(PasswordSetup::canResend('student-1'));
    }

    public function testCannotResendImmediatelyAfterSendingToken(): void
    {
        $this->createUser();

        PasswordSetup::createToken('student-1');

        $this->assertFalse(PasswordSetup::canResend('student-1'));
    }

    public function testCreatingNewTokenReplacesPreviousToken(): void
    {
        $this->createUser();

        $firstToken = PasswordSetup::createToken('student-1');
        $secondToken = PasswordSetup::createToken('student-1');

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertNull(PasswordSetup::findByToken($firstToken));

        $result = PasswordSetup::findByToken($secondToken);

        $this->assertIsArray($result);
        $this->assertSame('student-1', $result['userID']);
    }

    public function testSetsPasswordAndVerifiesEmail(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');
        $password = 'SecurePass123!';

        $result = PasswordSetup::setPassword($token, $password);

        $this->assertTrue($result);

        $stmt = $this->db->prepare(
            'SELECT password, isEmailVerified FROM Users WHERE userID = ?'
        );
        $stmt->execute(['student-1']);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue(password_verify($password, $user['password']));
        $this->assertSame(1, (int) $user['isEmailVerified']);
    }

    public function testDeletesTokenAfterSuccessfulPasswordSetup(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        PasswordSetup::setPassword($token, 'SecurePass123!');

        $this->assertNull(PasswordSetup::findByToken($token));
    }

    public function testCannotReuseTokenAfterPasswordSetup(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        $firstAttempt = PasswordSetup::setPassword(
            $token,
            'SecurePass123!'
        );

        $secondAttempt = PasswordSetup::setPassword(
            $token,
            'AnotherPass123!'
        );

        $this->assertTrue($firstAttempt);
        $this->assertFalse($secondAttempt);
    }

    public function testRejectsWeakPassword(): void
    {
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        $this->expectException(InvalidArgumentException::class);

        PasswordSetup::setPassword($token, 'weakpassword');
    }

    public function testDoesNotSetPasswordForDisabledStudent(): void
    {
        // the link goes out while they're enabled, then they get disabled before using it
        $this->createUser();

        $token = PasswordSetup::createToken('student-1');

        $this->db->exec("UPDATE Users SET isEnabled = 0 WHERE userID = 'student-1'");

        $result = PasswordSetup::setPassword(
            $token,
            'SecurePass123!'
        );

        $this->assertFalse($result);

        $stmt = $this->db->prepare(
            'SELECT isEmailVerified FROM Users WHERE userID = ?'
        );
        $stmt->execute(['student-1']);

        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testDoesNotSetPasswordForAccountThatAlreadyHasOne(): void
    {
        // a link can't be used to change the password of an account that's already set up
        $this->createUser(accountType: 'lecturer', isEmailVerified: 1);

        $token = PasswordSetup::makeToken();

        $stmt = $this->db->prepare("
            INSERT INTO PasswordSetupTokens (
                userID,
                tokenHash,
                expiresAt
            ) VALUES (?, ?, datetime('now', '+24 hours'))
        ");

        $stmt->execute([
            'student-1',
            PasswordSetup::hashToken($token),
        ]);

        $result = PasswordSetup::setPassword(
            $token,
            'SecurePass123!'
        );

        $this->assertFalse($result);
    }

    public function testLecturerAddedByAnAdminCanSetTheirPassword(): void
    {
        // Arrange, a lecturer account an admin has just made
        $this->createUser(userID: 'lecturer-1', accountType: 'lecturer');
        $token = PasswordSetup::createToken('lecturer-1');

        // Act
        $result = PasswordSetup::setPassword($token, 'SecurePass123!');

        // Assert
        $this->assertTrue($result);

        $stmt = $this->db->prepare('SELECT isEmailVerified FROM Users WHERE userID = ?');
        $stmt->execute(['lecturer-1']);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }
}
