<?php

use App\Models\BackupCode;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MFA backup codes
 * uses an in memory sqlite table like userTest.php, real db isnt touched
 */
final class BackupCodeTest extends TestCase
{
    private const USER_ID = 'test-user-id';

    /**
     * Fresh pretend table for each test
     */
    protected function setUp(): void
    {
        $db = new \PDO('sqlite::memory:');
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // sqlite makes the ID itself here, MariaDB uses uuid()
        $db->exec('CREATE TABLE MfaBackupCodes (backupCodeID INTEGER PRIMARY KEY, userID TEXT, codeHash TEXT)');

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $db);
    }

    /**
     * Unhooks the pretend database so the next test file starts clean
     */
    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    /**
     * 8 different codes like K7QM-2XPA, all saved (normal)
     */
    public function testNewSetHasEightDifferentCodes(): void
    {
        // Arrange: nothing, no codes yet

        // Act
        $codes = BackupCode::makeSet();
        BackupCode::saveSet(self::USER_ID, BackupCode::hashSet($codes));

        // Assert
        self::assertCount(BackupCode::SET_SIZE, array_unique($codes));
        self::assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $codes[0]);
        self::assertSame(BackupCode::SET_SIZE, BackupCode::countLeft(self::USER_ID));
    }

    /**
     * Works in lower case with a space instead of the dash, but only once (boundary)
     */
    public function testCodeOnlyWorksOnce(): void
    {
        // Arrange
        $codes = BackupCode::makeSet();
        BackupCode::saveSet(self::USER_ID, BackupCode::hashSet($codes));
        $typedSloppily = strtolower(str_replace('-', ' ', $codes[0]));

        // Act
        $firstGo = BackupCode::useCode(self::USER_ID, $typedSloppily);
        $secondGo = BackupCode::useCode(self::USER_ID, $codes[0]);

        // Assert
        self::assertTrue($firstGo);
        self::assertFalse($secondGo);
        self::assertSame(BackupCode::SET_SIZE - 1, BackupCode::countLeft(self::USER_ID));
    }

    /**
     * Made up or wrong shaped code gets refused (error)
     */
    public function testWrongCodeIsTurnedAway(): void
    {
        // Arrange
        BackupCode::saveSet(self::USER_ID, BackupCode::hashSet(BackupCode::makeSet()));

        // Act
        $isUsed = BackupCode::useCode(self::USER_ID, 'AAAA-AAAA');

        // Assert
        self::assertFalse($isUsed);
        self::assertFalse(BackupCode::isBackupCode('12345'));
        self::assertSame(BackupCode::SET_SIZE, BackupCode::countLeft(self::USER_ID));
    }
}
