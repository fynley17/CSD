<?php

use App\Models\Leaderboard;
use App\Models\Model;
use PHPUnit\Framework\TestCase;

final class LeaderboardModelTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Users (
                userID TEXT PRIMARY KEY,
                isEnabled INTEGER NOT NULL,
                approvalStatus TEXT NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE Students (
                userID TEXT PRIMARY KEY,
                firstName TEXT NOT NULL,
                lastName TEXT NOT NULL,
                points INTEGER NOT NULL,
                cohortID TEXT NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL,
                cohortID TEXT NOT NULL,
                isActive INTEGER NOT NULL
            )'
        );
        $this->db->exec('CREATE TABLE Tests (testID TEXT PRIMARY KEY, subjectID TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE CompletedTests (
                completedID TEXT PRIMARY KEY,
                userID TEXT NOT NULL,
                testID TEXT NOT NULL,
                pointsEarned INTEGER NOT NULL,
                percentageCorrect REAL NOT NULL
            )'
        );

        // Seven students in cohort-1, one in cohort-2 and one disabled account.
        $this->db->exec(
            "INSERT INTO Users VALUES
                ('s1', 1, 'approved'), ('s2', 1, 'approved'), ('s3', 1, 'approved'),
                ('s4', 1, 'approved'), ('s5', 1, 'approved'), ('s6', 1, 'approved'),
                ('s7', 1, 'approved'), ('other', 1, 'approved'), ('disabled', 0, 'approved');
             INSERT INTO Students VALUES
                ('s1', 'Alex', 'Brown', 300, 'cohort-1'),
                ('s2', 'Sam', 'Green', 500, 'cohort-1'),
                ('s3', 'Jo', 'White', 200, 'cohort-1'),
                ('s4', 'Kai', 'Black', 200, 'cohort-1'),
                ('s5', 'Rae', 'Grey', 100, 'cohort-1'),
                ('s6', 'Lee', 'Stone', 50, 'cohort-1'),
                ('s7', 'Noor', 'Hill', 0, 'cohort-1'),
                ('other', 'Max', 'Field', 900, 'cohort-2'),
                ('disabled', 'Ash', 'Lane', 800, 'cohort-1');
             INSERT INTO Subjects VALUES
                ('web', 'Web Apps', 'cohort-1', 1),
                ('cyber', 'Cyber Security', 'cohort-1', 1),
                ('old', 'Archived', 'cohort-1', 0),
                ('maths', 'Maths', 'cohort-2', 1);
             INSERT INTO Tests VALUES ('web-1', 'web'), ('cyber-1', 'cyber'), ('maths-1', 'maths');
             INSERT INTO CompletedTests VALUES
                ('c1', 's1', 'web-1', 300, 100),
                ('c2', 's2', 'cyber-1', 500, 90),
                ('c3', 's3', 'web-1', 200, 80),
                ('c4', 's4', 'web-1', 200, 60),
                ('c5', 's5', 'cyber-1', 100, 50),
                ('c6', 's6', 'web-1', 50, 40),
                ('c7', 's1', 'web-1', -10, 45),
                ('c8', 'other', 'maths-1', 900, 100),
                ('c9', 'disabled', 'web-1', 800, 100)"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testListsOnlyActiveSubjectsInTheStudentsCohort(): void
    {
        self::assertSame(
            [
                ['subjectID' => 'cyber', 'subjectName' => 'Cyber Security'],
                ['subjectID' => 'web', 'subjectName' => 'Web Apps'],
            ],
            Leaderboard::getSubjectsForStudent('s1')
        );
    }

    public function testOverallShowsTopFiveInTheCohortByTotalPoints(): void
    {
        $students = Leaderboard::getTopStudents('s1');

        // Ash is disabled, Max is in another cohort and Noor hasn't taken a test.
        self::assertSame(['Sam G.', 'Alex B.', 'Jo W.', 'Kai B.', 'Rae G.'], array_column($students, 'name'));
        self::assertSame([500, 300, 200, 200, 100], array_column($students, 'points'));
    }

    public function testEqualPointsShareARank(): void
    {
        $students = Leaderboard::getTopStudents('s1');

        self::assertSame([1, 2, 3, 3, 5], array_column($students, 'rank'));
    }

    public function testSubjectLeaderboardOnlyCountsThatSubjectsTests(): void
    {
        $students = Leaderboard::getTopStudents('s1', 'web');

        self::assertSame(['Alex B.', 'Jo W.', 'Kai B.', 'Lee S.'], array_column($students, 'name'));
        self::assertSame(
            [
                'rank' => 1,
                'name' => 'Alex B.',
                'isCurrentStudent' => true,
                'testsTaken' => 2,
                'averageScore' => 72.5,
                'points' => 290,
            ],
            $students[0]
        );
        self::assertFalse($students[1]['isCurrentStudent']);
    }

    public function testDoesNotSendStudentIDsOrSurnames(): void
    {
        $students = Leaderboard::getTopStudents('s1');

        self::assertSame(
            ['rank', 'name', 'isCurrentStudent', 'testsTaken', 'averageScore', 'points'],
            array_keys($students[0])
        );
    }
}
