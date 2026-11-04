<?php

use App\Controllers\AdministratorController;
use App\Models\Lecturer;
use App\Models\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the checks the lecturer management endpoints do before anything changes
 * jsonResponse() stops the script so the endpoints can't be called here, these call the checks they use instead
 * Tests are laid out Arrange, Act, Assert
 */
final class AdministratorControllerTest extends TestCase
{
    private \PDO $db;

    /**
     * Users table with just the columns findById() reads, one of each account type
     */
    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec(
            'CREATE TABLE Users (userID TEXT PRIMARY KEY, username TEXT, password TEXT, accountType TEXT, '
            . 'approvalStatus TEXT, isEnabled INTEGER, isEmailVerified INTEGER, failedAttempts INTEGER, '
            . 'lockedUntil TEXT)'
        );
        $this->db->exec(
            "INSERT INTO Users VALUES ('lecturer-id', 'lec01', 'x', 'lecturer', 'approved', 1, 1, 0, NULL), "
            . "('student-id', 'stu01', 'x', 'student', 'approved', 1, 1, 0, NULL)"
        );

        // points the models at the pretend database instead of the real one
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
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
     * A lecturer's ID finds them (normal)
     */
    public function testFindsALecturer(): void
    {
        // Arrange
        $userID = 'lecturer-id';

        // Act
        $lecturer = AdministratorController::findLecturer($userID);

        // Assert
        $this->assertInstanceOf(Lecturer::class, $lecturer);
        $this->assertSame('lecturer-id', $lecturer->getUserID());
    }

    /**
     * A student's ID or one that doesn't exist gives null, which the endpoints turn into a 404 (error)
     */
    #[DataProvider('notLecturerIDs')]
    public function testGivesNullWhenItIsNotALecturer(string $userID): void
    {
        // Arrange: $userID comes from notLecturerIDs()

        // Act
        $lecturer = AdministratorController::findLecturer($userID);

        // Assert
        $this->assertNull($lecturer);
    }

    /**
     * IDs that shouldn't find a lecturer
     */
    public static function notLecturerIDs(): array
    {
        return [
            'a student' => ['student-id'],
            'nobody has it' => ['unknown-id'],
        ];
    }

    /**
     * true and false come back as they are (normal)
     */
    public function testReadsTrueAndFalse(): void
    {
        // Arrange
        $enable = ['isEnabled' => true];
        $disable = ['isEnabled' => false];

        // Act
        $enableResult = AdministratorController::readIsEnabled($enable);
        $disableResult = AdministratorController::readIsEnabled($disable);

        // Assert
        $this->assertTrue($enableResult);
        $this->assertFalse($disableResult);
    }

    /**
     * Anything that isn't really true or false gives null, which turns into a 400 (error)
     */
    #[DataProvider('badIsEnabled')]
    public function testGivesNullForAnythingElse(array $data): void
    {
        // Arrange: $data comes from badIsEnabled()

        // Act
        $isEnabled = AdministratorController::readIsEnabled($data);

        // Assert
        $this->assertNull($isEnabled);
    }

    /**
     * What someone skipping the page could send instead of true or false
     */
    public static function badIsEnabled(): array
    {
        return [
            'missing' => [[]],
            'the word false' => [['isEnabled' => 'false']],
            'a number' => [['isEnabled' => 1]],
            'null' => [['isEnabled' => null]],
        ];
    }
}
