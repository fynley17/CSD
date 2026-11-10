
<?php

use App\Models\CompletedTest;
use App\Models\Model;
use App\Models\Test;
use PHPUnit\Framework\TestCase;

final class CompletedTestTest extends TestCase
{
    // Every question in test-1's pool, in the order they were drawn.
    private const ALL_QUESTIONS = ['question-2', 'question-1', 'question-3'];

    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Tests (
                testID TEXT PRIMARY KEY,
                testName TEXT NOT NULL,
                testDescription TEXT,
                timeLimit INTEGER,
                questionCount INTEGER,
                questionSelectionMethod TEXT NOT NULL DEFAULT "random",
                subjectID TEXT NOT NULL,
                isActive INTEGER NOT NULL DEFAULT 1
            )'
        );

        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL,
                cohortID TEXT,
                isActive INTEGER NOT NULL DEFAULT 1
            )'
        );

        $this->db->exec(
            'CREATE TABLE Students (
                userID TEXT PRIMARY KEY,
                cohortID TEXT NOT NULL,
                points INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->db->exec(
            'CREATE TABLE Questions (
                questionID TEXT PRIMARY KEY,
                subjectID TEXT NOT NULL,
                question TEXT NOT NULL,
                percentageCorrect INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->db->exec(
            'CREATE TABLE Answers (
                answerID TEXT PRIMARY KEY,
                questionID TEXT NOT NULL,
                answerText TEXT NOT NULL,
                isCorrect INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->db->exec(
            'CREATE TABLE CompletedTests (
                completedID TEXT PRIMARY KEY,
                userID TEXT NOT NULL,
                testID TEXT NOT NULL,
                completedDate TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                questionCount INTEGER NOT NULL,
                pointsEarned INTEGER NOT NULL DEFAULT 0,
                percentageCorrect REAL NOT NULL DEFAULT 0
            )'
        );

        $this->db->exec(
            'CREATE TABLE StudentAnswers (
                completedID TEXT NOT NULL,
                questionID TEXT NOT NULL,
                selectedAnswerID TEXT,
                pointsAwarded INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (completedID, questionID)
            )'
        );

        // Test-1's pool contains three Web Apps questions.
        // Question-4 belongs to another subject.
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName, cohortID) VALUES
                ('subject-1', 'Web Apps', 'cohort-1'),
                ('subject-2', 'Databases', 'cohort-1');

             INSERT INTO Students VALUES
                ('student-1', 'cohort-1', 100),
                ('student-2', 'cohort-2', 0);

             INSERT INTO Tests (
                testID,
                testName,
                testDescription,
                timeLimit,
                questionCount,
                subjectID
             ) VALUES (
                'test-1',
                'Web Apps Test',
                'Review',
                5,
                10,
                'subject-1'
             );

             INSERT INTO Questions (questionID, subjectID, question) VALUES
                ('question-1', 'subject-1', 'What is not a JS framework?'),
                ('question-2', 'subject-1', 'What does HTML stand for?'),
                ('question-3', 'subject-1', 'Which is a CSS unit?'),
                ('question-4', 'subject-2', 'Which SQL statement reads data?');

             INSERT INTO Answers VALUES
                ('a1', 'question-1', 'Django', 1),
                ('a2', 'question-1', 'Vue', 0),
                ('b1', 'question-2', 'HyperText Markup Language', 1),
                ('b2', 'question-2', 'High Tech Modern Language', 0),
                ('c1', 'question-3', 'rem', 1),
                ('c2', 'question-3', 'kg', 0),
                ('d1', 'question-4', 'SELECT', 1)"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testDrawsOnlyFromTheSubjectsPoolWithoutCorrectAnswers(): void
    {
        // Arrange: setUp() has filled the tables.

        // Act
        $test = Test::getForStudent('test-1', 'student-1');

        // Assert
        self::assertSame('Web Apps Test', $test['testName']);
        self::assertSame('Web Apps', $test['subjectName']);
        self::assertSame(5, $test['timeLimit']);
        self::assertSame(3, $test['questionCount']);
        self::assertArrayNotHasKey('questionsPerAttempt', $test);
        self::assertArrayNotHasKey('subjectIsActive', $test);

        $questionIDs = array_column($test['questions'], 'questionID');
        sort($questionIDs);

        self::assertSame(
            ['question-1', 'question-2', 'question-3'],
            $questionIDs
        );

        self::assertSame(
            ['answerID', 'answerText'],
            array_keys($test['questions'][0]['answers'][0])
        );

        self::assertTrue($test['earnsPoints']);
    }

    public function testDrawsTheTestsQuestionCountAtRandomFromThePool(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Tests SET questionCount = 2 WHERE testID = 'test-1'"
        );

        // Act
        // Enough draws that every question should appear at least once.
        $tests = [];

        for ($i = 0; $i < 50; $i++) {
            $tests[] = Test::getForStudent('test-1', 'student-1');
        }

        // Assert
        $drawn = [];

        foreach ($tests as $test) {
            $questionIDs = array_column($test['questions'], 'questionID');

            self::assertSame(2, $test['questionCount']);
            self::assertCount(2, array_unique($questionIDs));

            $drawn = array_merge($drawn, $questionIDs);
        }

        $drawn = array_values(array_unique($drawn));
        sort($drawn);

        self::assertSame(
            ['question-1', 'question-2', 'question-3'],
            $drawn
        );
    }


    public function testPersonalisedSelectionStartsAfterThreeCompletedAttempts(): void
    {
        $this->db->exec(
            "UPDATE Tests
         SET questionSelectionMethod = 'frequently_incorrect',
             questionCount = 1
         WHERE testID = 'test-1'"
        );

        // Two completed attempts should not activate personalisation.
        for ($i = 0; $i < 2; $i++) {
            $this->db->exec(
                "INSERT INTO CompletedTests (
                completedID, userID, testID, questionCount
            ) VALUES (
                'completed-$i', 'student-1', 'test-1', 1
            )"
            );
        }

        $test = Test::getForStudent('test-1', 'student-1');

        self::assertCount(1, $test['questions']);

        // The third completed attempt activates personalisation.
        $this->db->exec(
            "INSERT INTO CompletedTests (
            completedID, userID, testID, questionCount
        ) VALUES (
            'completed-3', 'student-1', 'test-1', 1
        )"
        );

        $test = Test::getForStudent('test-1', 'student-1');

        self::assertCount(1, $test['questions']);
    }


    public function testFrequentlyIncorrectSelectionUsesIndividualAnswerHistory(): void
    {
        $this->db->exec(
            "UPDATE Tests
         SET questionSelectionMethod = 'frequently_incorrect',
             questionCount = 1
         WHERE testID = 'test-1'"
        );

        for ($i = 1; $i <= 3; $i++) {
            $completedID = "completed-$i";

            $this->db->prepare(
                "INSERT INTO CompletedTests (
                completedID, userID, testID, questionCount
            ) VALUES (?, 'student-1', 'test-1', 3)"
            )->execute([$completedID]);

            $this->db->prepare(
                "INSERT INTO StudentAnswers (
                completedID, questionID, selectedAnswerID
            ) VALUES (?, 'question-1', 'a2')"
            )->execute([$completedID]);

            $this->db->prepare(
                "INSERT INTO StudentAnswers (
                completedID, questionID, selectedAnswerID
            ) VALUES (?, 'question-2', 'b1')"
            )->execute([$completedID]);
        }

        $drawn = [];

        for ($i = 0; $i < 200; $i++) {
            $test = Test::getForStudent('test-1', 'student-1');

            $questionID = $test['questions'][0]['questionID'];

            $drawn[$questionID] = ($drawn[$questionID] ?? 0) + 1;
        }

        self::assertGreaterThan(
            $drawn['question-2'] ?? 0,
            $drawn['question-1'] ?? 0
        );
    }


    public function testNeverDrawsMoreQuestionsThanThePoolHolds(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Tests SET questionCount = 10 WHERE testID = 'test-1'"
        );

        // Act
        $test = Test::getForStudent('test-1', 'student-1');

        // Assert
        self::assertSame(3, $test['questionCount']);
    }

    public function testDoesNotLoadATestWhoseSubjectHasNoQuestions(): void
    {
        // Arrange
        $this->db->exec(
            "DELETE FROM Questions WHERE subjectID = 'subject-1'"
        );

        // Act
        $test = Test::getForStudent('test-1', 'student-1');

        // Assert
        self::assertNull($test);
    }

    public function testDoesNotLoadATestFromAnotherCohort(): void
    {
        // Arrange: student-2 is in another cohort.

        // Act
        $otherCohortTest = Test::getForStudent('test-1', 'student-2');
        $missingTest = Test::getForStudent('missing-test', 'student-1');

        // Assert
        self::assertNull($otherCohortTest);
        self::assertNull($missingTest);
    }

    public function testDoesNotLoadATestWhoseSubjectIsArchived(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Subjects SET isActive = 0 WHERE subjectID = 'subject-1'"
        );

        // Act
        $test = Test::getForStudent('test-1', 'student-1');

        // Assert
        self::assertNull($test);
    }

    public function testDoesNotLoadAnArchivedTestForAStudent(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Tests SET isActive = 0 WHERE testID = 'test-1'"
        );

        // Act
        $test = Test::getForStudent('test-1', 'student-1');

        // Assert
        self::assertNull($test);
    }

    public function testStartedAttemptCanStillBeHandedInAfterItsSubjectIsArchived(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Subjects SET isActive = 0 WHERE subjectID = 'subject-1'"
        );

        // Act
        $result = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            ['question-1' => 'a1']
        );

        // Assert
        self::assertNotNull($result);

        self::assertEquals(
            1,
            $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn()
        );
    }

    public function testStartedAttemptCanStillBeHandedInAfterItsTestIsArchived(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Tests SET isActive = 0 WHERE testID = 'test-1'"
        );

        // Act
        $result = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            ['question-1' => 'a1']
        );

        // Assert
        self::assertNotNull($result);

        self::assertEquals(
            1,
            $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn()
        );
    }

    public function testStartedAttemptCanStillBeHandedInAfterItsSubjectIsRemovedFromTheCohort(): void
    {
        // Arrange
        $this->db->exec("UPDATE Subjects SET cohortID = NULL WHERE subjectID = 'subject-1'");

        // Act
        $result = CompletedTest::submit('student-1', 'test-1', self::ALL_QUESTIONS, ['question-1' => 'a1']);

        // Assert
        self::assertSame(1, $result['correctCount']);
        self::assertEquals(1, $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn());
        self::assertEquals(
            100 + $result['pointsEarned'],
            $this->db->query("SELECT points FROM Students WHERE userID = 'student-1'")->fetchColumn()
        );
    }

    public function testStartedAttemptCanStillBeHandedInAfterItsSubjectMovesToAnotherCohort(): void
    {
        // Arrange
        $this->db->exec("UPDATE Subjects SET cohortID = 'cohort-2' WHERE subjectID = 'subject-1'");

        // Act
        $result = CompletedTest::submit('student-1', 'test-1', self::ALL_QUESTIONS, ['question-1' => 'a1']);

        // Assert
        self::assertNotNull($result);
    }

    public function testNewAttemptCannotStartAfterItsSubjectIsRemovedFromTheCohort(): void
    {
        // Arrange
        $this->db->exec("UPDATE Subjects SET cohortID = NULL WHERE subjectID = 'subject-1'");

        // Act
        $test = Test::getForStudent('test-1', 'student-1');

        // Assert
        self::assertNull($test);
    }

    public function testHandingInATestThatNoLongerExistsGivesNull(): void
    {
        // Act
        $result = CompletedTest::submit('student-1', 'missing-test', self::ALL_QUESTIONS, []);

        // Assert
        self::assertNull($result);
    }

    public function testStartedLookupSkipsTheCohortCheckThatOpeningATestHas(): void
    {
        // Arrange: setUp() puts student-2 in another cohort

        // Act
        $opening = Test::findForStudent('test-1', 'student-2');
        $handingIn = Test::findStartedByStudent('test-1', 'student-2');

        // Assert
        self::assertNull($opening);
        self::assertSame('Web Apps Test', $handingIn['testName']);
    }

    public function testStartedLookupGivesNullForATestThatDoesNotExist(): void
    {
        // Act
        $test = Test::findStartedByStudent('missing-test', 'student-1');

        // Assert
        self::assertNull($test);
    }

    public function testMarksAndSavesTheAttempt(): void
    {
        // Arrange
        // One correct, one incorrect, one unanswered.
        $answers = [
            'question-1' => 'a1',
            'question-2' => 'b2',
            'question-3' => null,
        ];

        // Act
        $result = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            $answers
        );

        // Assert
        self::assertSame(3, $result['questionCount']);
        self::assertSame(1, $result['correctCount']);
        self::assertSame(20, $result['pointsEarned']);
        self::assertEquals(33.33, $result['percentageCorrect']);
        self::assertTrue($result['earnsPoints']);

        // The review is in question order and shows which answer is correct.
        $review = $result['questions'];

        self::assertSame(
            ['question-2', 'question-1', 'question-3'],
            array_column($review, 'questionID')
        );

        self::assertSame(
            [false, true, false],
            array_column($review, 'isCorrect')
        );

        self::assertSame(
            [-10, 30, 0],
            array_column($review, 'pointsAwarded')
        );

        self::assertTrue($review[0]['answers'][0]['isCorrect']);

        $saved = $this->db->query(
            'SELECT * FROM CompletedTests'
        )->fetch(\PDO::FETCH_ASSOC);

        self::assertSame($result['completedID'], $saved['completedID']);
        self::assertSame('student-1', $saved['userID']);
        self::assertEquals(20, $saved['pointsEarned']);

        $answers = $this->db->query(
            'SELECT questionID, selectedAnswerID, pointsAwarded
             FROM StudentAnswers
             ORDER BY questionID'
        )->fetchAll(\PDO::FETCH_NUM);

        self::assertEquals(
            [
                ['question-1', 'a1', 30],
                ['question-2', 'b2', -10],
                ['question-3', null, 0],
            ],
            $answers
        );

        $points = $this->db->query(
            "SELECT points FROM Students WHERE userID = 'student-1'"
        )->fetchColumn();

        self::assertEquals(120, $points);
    }

    public function testUpdatesEachQuestionsPercentageCorrectAcrossAttempts(): void
    {
        // Arrange
        // Student-2's cohort can see the test too for this check.
        $this->db->exec(
            "UPDATE Students SET cohortID = 'cohort-1' WHERE userID = 'student-2'"
        );

        // Act
        CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            ['question-1' => 'a1', 'question-2' => 'b2']
        );

        $afterFirstAttempt = $this->percentages();

        // Question-3 is unanswered in the first attempt, so it isn't counted.
        CompletedTest::submit(
            'student-2',
            'test-1',
            self::ALL_QUESTIONS,
            [
                'question-1' => 'a2',
                'question-2' => 'b1',
                'question-3' => 'c1',
            ]
        );

        CompletedTest::submit(
            'student-2',
            'test-1',
            self::ALL_QUESTIONS,
            ['question-3' => 'c1']
        );

        CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            ['question-3' => 'c2']
        );

        $afterEveryAttempt = $this->percentages();

        // Assert
        self::assertSame(
            ['question-1' => 100, 'question-2' => 0, 'question-3' => 0],
            $afterFirstAttempt
        );

        self::assertSame(
            ['question-1' => 50, 'question-2' => 50, 'question-3' => 67],
            $afterEveryAttempt
        );
    }

    /**
     * @return array<string, int> questionID => percentageCorrect for test-1's subject
     */
    private function percentages(): array
    {
        $rows = $this->db->query(
            "SELECT questionID, percentageCorrect
             FROM Questions
             WHERE subjectID = 'subject-1'
             ORDER BY questionID"
        );

        return array_map('intval', $rows->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    public function testOnlyMarksTheQuestionsDrawnForTheAttempt(): void
    {
        // Arrange
        // Question-1 wasn't drawn, so its answer is ignored.
        $answers = [
            'question-1' => 'a1',
            'question-2' => 'b1',
            'question-3' => 'c1',
        ];

        // Act
        $result = CompletedTest::submit(
            'student-1',
            'test-1',
            ['question-3', 'question-2'],
            $answers
        );

        // Assert
        self::assertSame(2, $result['questionCount']);
        self::assertSame(2, $result['correctCount']);
        self::assertSame(60, $result['pointsEarned']);
        self::assertEquals(100, $result['percentageCorrect']);

        self::assertSame(
            ['question-3', 'question-2'],
            array_column($result['questions'], 'questionID')
        );

        self::assertEquals(
            2,
            $this->db->query('SELECT COUNT(*) FROM StudentAnswers')->fetchColumn()
        );
    }

    public function testRejectsAnAttemptWithNoQuestionsFromTheTestsSubject(): void
    {
        // Arrange: question-4 belongs to another subject.
        $this->expectException(\InvalidArgumentException::class);

        // Act
        CompletedTest::submit(
            'student-1',
            'test-1',
            ['question-4'],
            ['question-4' => 'd1']
        );
    }

    public function testMissingAnswersCountAsUnanswered(): void
    {
        // Arrange
        $noAnswers = [];

        // Act
        $result = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            $noAnswers
        );

        // Assert
        self::assertSame(0, $result['correctCount']);
        self::assertSame(0, $result['pointsEarned']);

        self::assertSame(
            [null, null, null],
            array_column($result['questions'], 'selectedAnswerID')
        );
    }

    public function testEachRetakeIsSavedAsItsOwnAttempt(): void
    {
        // Arrange
        $answers = ['question-1' => 'a1'];

        // Act
        $first = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            $answers
        );

        $second = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            $answers
        );

        // Assert
        self::assertNotSame($first['completedID'], $second['completedID']);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $first['completedID']
        );

        self::assertEquals(
            2,
            $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn()
        );
    }

    public function testCanRetakeAfterFullMarksButEarnsNoMorePoints(): void
    {
        // Arrange
        $fullMarks = [
            'question-1' => 'a1',
            'question-2' => 'b1',
            'question-3' => 'c1',
        ];

        $oneRight = [
            'question-1' => 'a1',
            'question-2' => 'b2',
        ];

        // Act
        $result = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            $fullMarks
        );

        $test = Test::getForStudent('test-1', 'student-1');

        $retake = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            $oneRight
        );

        // Assert
        self::assertEquals(100, $result['percentageCorrect']);
        self::assertSame(90, $result['pointsEarned']);

        // The test can still be opened, but says the attempt won't earn points.
        self::assertNotNull($test);
        self::assertFalse($test['earnsPoints']);

        // The retake is marked and saved, but every question scores 0.
        self::assertFalse($retake['earnsPoints']);
        self::assertSame(1, $retake['correctCount']);
        self::assertEquals(33.33, $retake['percentageCorrect']);
        self::assertSame(0, $retake['pointsEarned']);

        self::assertSame(
            [0, 0, 0],
            array_column($retake['questions'], 'pointsAwarded')
        );

        // Review is in the drawn order.
        self::assertSame(
            [false, true, false],
            array_column($retake['questions'], 'isCorrect')
        );

        self::assertEquals(
            2,
            $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn()
        );

        $saved = $this->db->query(
            "SELECT pointsAwarded
             FROM StudentAnswers
             WHERE completedID = '{$retake['completedID']}'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        self::assertEquals([0, 0, 0], $saved);

        // Only the first attempt's points were added to the student's total.
        $points = $this->db->query(
            "SELECT points FROM Students WHERE userID = 'student-1'"
        )->fetchColumn();

        self::assertEquals(190, $points);
    }

    public function testFullMarksOnAnotherStudentsAttemptDoesNotStopYourPoints(): void
    {
        // Arrange
        $this->db->exec(
            "UPDATE Students SET cohortID = 'cohort-1' WHERE userID = 'student-2'"
        );

        $answers = [
            'question-1' => 'a1',
            'question-2' => 'b1',
            'question-3' => 'c1',
        ];

        CompletedTest::submit(
            'student-2',
            'test-1',
            self::ALL_QUESTIONS,
            $answers
        );

        // Act
        $result = CompletedTest::submit(
            'student-1',
            'test-1',
            self::ALL_QUESTIONS,
            $answers
        );

        // Assert
        self::assertTrue($result['earnsPoints']);
        self::assertSame(90, $result['pointsEarned']);
    }

    public function testRejectsAnAnswerFromADifferentQuestion(): void
    {
        // Arrange
        $answers = ['question-1' => 'b1'];
        $exception = null;

        // Act
        try {
            CompletedTest::submit(
                'student-1',
                'test-1',
                self::ALL_QUESTIONS,
                $answers
            );
        } catch (\InvalidArgumentException $caught) {
            $exception = $caught;
        }

        // Assert
        self::assertInstanceOf(\InvalidArgumentException::class, $exception);

        // Nothing is saved when the submission is rejected.
        self::assertEquals(
            0,
            $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn()
        );
    }
}
