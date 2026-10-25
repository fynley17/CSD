<?php

use App\Models\CompletedTest;
use App\Models\Model;
use App\Models\Test;
use PHPUnit\Framework\TestCase;

final class CompletedTestTest extends TestCase
{
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
                subjectID TEXT NOT NULL
            )'
        );
        $this->db->exec('CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL, cohortID TEXT)');
        $this->db->exec(
            'CREATE TABLE Students (userID TEXT PRIMARY KEY, cohortID TEXT NOT NULL, points INTEGER NOT NULL DEFAULT 0)'
        );
        $this->db->exec(
            'CREATE TABLE Questions (
                questionID TEXT PRIMARY KEY,
                question TEXT NOT NULL,
                percentageCorrect INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->db->exec(
            'CREATE TABLE TestQuestions (
                testID TEXT NOT NULL,
                questionID TEXT NOT NULL,
                questionOrder INTEGER NOT NULL
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

        // question-2 is listed first by questionOrder, so ordering can be checked
        $this->db->exec(
            "INSERT INTO Subjects VALUES ('subject-1', 'Web Apps', 'cohort-1');
             INSERT INTO Students VALUES ('student-1', 'cohort-1', 100), ('student-2', 'cohort-2', 0);
             INSERT INTO Tests VALUES ('test-1', 'Web Apps Test', 'Review', 5, 'subject-1');
             INSERT INTO Questions (questionID, question) VALUES
                ('question-1', 'What is not a JS framework?'),
                ('question-2', 'What does HTML stand for?'),
                ('question-3', 'Which is a CSS unit?');
             INSERT INTO TestQuestions VALUES
                ('test-1', 'question-1', 2),
                ('test-1', 'question-2', 1),
                ('test-1', 'question-3', 3);
             INSERT INTO Answers VALUES
                ('a1', 'question-1', 'Django', 1),
                ('a2', 'question-1', 'Vue', 0),
                ('b1', 'question-2', 'HyperText Markup Language', 1),
                ('b2', 'question-2', 'High Tech Modern Language', 0),
                ('c1', 'question-3', 'rem', 1),
                ('c2', 'question-3', 'kg', 0)"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testLoadsTheTestInQuestionOrderWithoutCorrectAnswers(): void
    {
        $test = Test::getForStudent('test-1', 'student-1');

        self::assertSame('Web Apps Test', $test['testName']);
        self::assertSame('Web Apps', $test['subjectName']);
        self::assertSame(5, $test['timeLimit']);
        self::assertSame(3, $test['questionCount']);
        self::assertSame(['question-2', 'question-1', 'question-3'], array_column($test['questions'], 'questionID'));
        self::assertSame(['answerID', 'answerText'], array_keys($test['questions'][0]['answers'][0]));
    }

    public function testDoesNotLoadATestFromAnotherCohort(): void
    {
        self::assertNull(Test::getForStudent('test-1', 'student-2'));
        self::assertNull(Test::getForStudent('missing-test', 'student-1'));
    }

    public function testMarksAndSavesTheAttempt(): void
    {
        // one correct, one incorrect, one unanswered
        $result = CompletedTest::submit('student-1', 'test-1', [
            'question-1' => 'a1',
            'question-2' => 'b2',
            'question-3' => null,
        ]);

        self::assertSame(3, $result['questionCount']);
        self::assertSame(1, $result['correctCount']);
        self::assertSame(20, $result['pointsEarned']);
        self::assertEquals(33.33, $result['percentageCorrect']);

        // the review is in question order and now shows which answer is correct
        $review = $result['questions'];
        self::assertSame(['question-2', 'question-1', 'question-3'], array_column($review, 'questionID'));
        self::assertSame([false, true, false], array_column($review, 'isCorrect'));
        self::assertSame([-10, 30, 0], array_column($review, 'pointsAwarded'));
        self::assertTrue($review[0]['answers'][0]['isCorrect']);

        $saved = $this->db->query('SELECT * FROM CompletedTests')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame($result['completedID'], $saved['completedID']);
        self::assertSame('student-1', $saved['userID']);
        self::assertEquals(20, $saved['pointsEarned']);

        $answers = $this->db->query(
            'SELECT questionID, selectedAnswerID, pointsAwarded FROM StudentAnswers ORDER BY questionID'
        )->fetchAll(\PDO::FETCH_NUM);
        self::assertEquals(
            [['question-1', 'a1', 30], ['question-2', 'b2', -10], ['question-3', null, 0]],
            $answers
        );

        $points = $this->db->query("SELECT points FROM Students WHERE userID = 'student-1'")->fetchColumn();
        self::assertEquals(120, $points);
    }

    public function testUpdatesEachQuestionsPercentageCorrectAcrossAttempts(): void
    {
        // student-2's cohort can see the test too for this check
        $this->db->exec("UPDATE Students SET cohortID = 'cohort-1' WHERE userID = 'student-2'");

        CompletedTest::submit('student-1', 'test-1', ['question-1' => 'a1', 'question-2' => 'b2']);
        self::assertSame(['question-1' => 100, 'question-2' => 0, 'question-3' => 0], $this->percentages());

        // 1 of 2 right for question-1, 1 of 2 for question-2, 2 of 3 for question-3,
        // and the unanswered question-3 from the first attempt isn't counted
        CompletedTest::submit(
            'student-2',
            'test-1',
            ['question-1' => 'a2', 'question-2' => 'b1', 'question-3' => 'c1']
        );
        CompletedTest::submit('student-2', 'test-1', ['question-3' => 'c1']);
        CompletedTest::submit('student-1', 'test-1', ['question-3' => 'c2']);

        self::assertSame(['question-1' => 50, 'question-2' => 50, 'question-3' => 67], $this->percentages());
    }

    /**
     * @return array<string, int> questionID => percentageCorrect
     */
    private function percentages(): array
    {
        $rows = $this->db->query('SELECT questionID, percentageCorrect FROM Questions ORDER BY questionID');
        return array_map('intval', $rows->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    public function testMissingAnswersCountAsUnanswered(): void
    {
        $result = CompletedTest::submit('student-1', 'test-1', []);

        self::assertSame(0, $result['correctCount']);
        self::assertSame(0, $result['pointsEarned']);
        self::assertSame([null, null, null], array_column($result['questions'], 'selectedAnswerID'));
    }

    public function testEachRetakeIsSavedAsItsOwnAttempt(): void
    {
        $first = CompletedTest::submit('student-1', 'test-1', ['question-1' => 'a1']);
        $second = CompletedTest::submit('student-1', 'test-1', ['question-1' => 'a1']);

        self::assertNotSame($first['completedID'], $second['completedID']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $first['completedID']
        );
        self::assertEquals(2, $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn());
    }

    public function testCannotOpenOrRetakeATestAfterFullMarks(): void
    {
        $answers = ['question-1' => 'a1', 'question-2' => 'b1', 'question-3' => 'c1'];
        $result = CompletedTest::submit('student-1', 'test-1', $answers);
        self::assertEquals(100, $result['percentageCorrect']);

        // a second tab or a typed URL can't earn the points again
        self::assertNull(Test::getForStudent('test-1', 'student-1'));
        self::assertNull(CompletedTest::submit('student-1', 'test-1', $answers));

        self::assertEquals(1, $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn());
        $points = $this->db->query("SELECT points FROM Students WHERE userID = 'student-1'")->fetchColumn();
        self::assertEquals(190, $points);
    }

    public function testRejectsAnAnswerFromADifferentQuestion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            CompletedTest::submit('student-1', 'test-1', ['question-1' => 'b1']);
        } finally {
            // nothing is saved when the submission is rejected
            self::assertEquals(0, $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn());
        }
    }

    public function testDoesNotSaveATestFromAnotherCohort(): void
    {
        self::assertNull(CompletedTest::submit('student-2', 'test-1', ['question-1' => 'a1']));
        self::assertEquals(0, $this->db->query('SELECT COUNT(*) FROM CompletedTests')->fetchColumn());
    }
}
