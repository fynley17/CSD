<?php

use App\Models\Model;
use App\Models\Result;
use PHPUnit\Framework\TestCase;

final class ResultReviewTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = new \PDO('sqlite::memory:');
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Tests (testID TEXT PRIMARY KEY, testName TEXT NOT NULL, subjectID TEXT NOT NULL)'
        );
        $this->db->exec('CREATE TABLE Subjects (subjectID TEXT PRIMARY KEY, subjectName TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE Students (userID TEXT PRIMARY KEY, firstName TEXT NOT NULL, lastName TEXT NOT NULL)'
        );
        $this->db->exec(
            'CREATE TABLE Questions (questionID TEXT PRIMARY KEY, subjectID TEXT NOT NULL, question TEXT NOT NULL)'
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
                completedDate TEXT NOT NULL,
                questionCount INTEGER NOT NULL,
                pointsEarned INTEGER NOT NULL,
                percentageCorrect REAL NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE StudentAnswers (
                completedID TEXT NOT NULL,
                questionID TEXT NOT NULL,
                selectedAnswerID TEXT,
                pointsAwarded INTEGER NOT NULL DEFAULT 0
            )'
        );

        // completed-1 has one right, one wrong and one unanswered question, question-4 wasn't drawn
        $this->db->exec(
            "INSERT INTO Subjects VALUES ('subject-1', 'Web Apps');
             INSERT INTO Students VALUES ('student-1', 'Ada', 'Lovelace'), ('student-2', 'Alan', 'Turing');
             INSERT INTO Tests VALUES ('test-1', 'Web Apps Test', 'subject-1');
             INSERT INTO Questions VALUES
                ('question-1', 'subject-1', 'B: What is not a JS framework?'),
                ('question-2', 'subject-1', 'A: What does HTML stand for?'),
                ('question-3', 'subject-1', 'C: Which is a CSS unit?'),
                ('question-4', 'subject-1', 'D: Which tag makes a link?');
             INSERT INTO Answers VALUES
                ('a1', 'question-1', 'Django', 1),
                ('a2', 'question-1', 'Vue', 0),
                ('b1', 'question-2', 'HyperText Markup Language', 1),
                ('b2', 'question-2', 'High Tech Modern Language', 0),
                ('c1', 'question-3', 'rem', 1),
                ('c2', 'question-3', 'kg', 0),
                ('d1', 'question-4', '<a>', 1);
             INSERT INTO CompletedTests VALUES
                ('completed-1', 'student-1', 'test-1', '2026-10-20 09:00:00', 3, 20, 33.33);
             INSERT INTO StudentAnswers VALUES
                ('completed-1', 'question-1', 'a2', -10),
                ('completed-1', 'question-2', 'b1', 30),
                ('completed-1', 'question-3', NULL, 0)"
        );

        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        $dbProperty = new \ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsTheAttemptWithTheStudentsName(): void
    {
        $review = Result::getReviewForStudent('completed-1', 'student-1');

        self::assertSame('Web Apps Test', $review['testName']);
        self::assertSame('Web Apps', $review['subjectName']);
        self::assertSame('Ada Lovelace', $review['studentName']);
        self::assertSame(3, $review['questionCount']);
        self::assertSame(1, $review['correctCount']);
        self::assertSame(20, $review['pointsEarned']);
        self::assertSame(33.33, $review['percentageCorrect']);
    }

    public function testReturnsOnlyTheQuestionsAskedWithTheirMarking(): void
    {
        $questions = Result::getReviewForStudent('completed-1', 'student-1')['questions'];

        self::assertSame(['question-2', 'question-1', 'question-3'], array_column($questions, 'questionID'));
        self::assertSame([true, false, false], array_column($questions, 'isCorrect'));
        self::assertSame(['b1', 'a2', null], array_column($questions, 'selectedAnswerID'));
        self::assertSame([30, -10, 0], array_column($questions, 'pointsAwarded'));
        self::assertSame(
            [
                ['answerID' => 'a1', 'answerText' => 'Django', 'isCorrect' => true],
                ['answerID' => 'a2', 'answerText' => 'Vue', 'isCorrect' => false],
            ],
            $questions[1]['answers']
        );
    }

    public function testDoesNotReturnAnotherStudentsAttempt(): void
    {
        self::assertNull(Result::getReviewForStudent('completed-1', 'student-2'));
        self::assertNull(Result::getReviewForStudent('missing', 'student-1'));
    }
}
