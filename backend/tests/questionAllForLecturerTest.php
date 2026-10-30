<?php

use App\Models\Model;
use App\Models\Question;
use PHPUnit\Framework\TestCase;

final class QuestionAllForLecturerTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        // Arrange: create an isolated in-memory SQLite database.
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the minimum Subjects table structure required by the query.
        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL
            )'
        );

        // Create the Questions table used by the Question model.
        $this->db->exec(
            'CREATE TABLE Questions (
                questionID TEXT PRIMARY KEY,
                subjectID TEXT NOT NULL,
                question TEXT NOT NULL,
                percentageCorrect INTEGER NOT NULL DEFAULT 0
            )'
        );

        // Create the Answers table used to retrieve answer options.
        $this->db->exec(
            'CREATE TABLE Answers (
                answerID TEXT PRIMARY KEY,
                questionID TEXT NOT NULL,
                answerText TEXT NOT NULL,
                isCorrect INTEGER NOT NULL DEFAULT 0
            )'
        );

        // Give the Question model the temporary database connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        // Clean up: prevent other tests from reusing this connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    public function testReturnsQuestionsWithSubjectsAndAnswers(): void
    {
        // Arrange: create two subjects.
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName) VALUES
                ('subject-1', 'Mathematics'),
                ('subject-2', 'Science')"
        );

        // Arrange: create questions with different percentages.
        $this->db->exec(
            "INSERT INTO Questions
                (questionID, subjectID, question, percentageCorrect)
             VALUES
                ('question-1', 'subject-1', 'What is 2 + 2?', 80),
                ('question-2', 'subject-2', 'Which planet is known as the Red Planet?', 65)"
        );

        // Arrange: add answer options and mark the correct answers.
        $this->db->exec(
            "INSERT INTO Answers
                (answerID, questionID, answerText, isCorrect)
             VALUES
                ('answer-1', 'question-1', '3', 0),
                ('answer-2', 'question-1', '4', 1),
                ('answer-3', 'question-2', 'Earth', 0),
                ('answer-4', 'question-2', 'Mars', 1)"
        );

        // Act: retrieve all questions using the real Question model.
        $questions = Question::getAllForLecturer();

        // Assert: both questions are returned in alphabetical order.
        self::assertCount(2, $questions);
        self::assertSame(
            [
                'What is 2 + 2?',
                'Which planet is known as the Red Planet?',
            ],
            array_column($questions, 'question')
        );

        // Assert: the subject names are included.
        self::assertSame('Mathematics', $questions[0]['subjectName']);
        self::assertSame('Science', $questions[1]['subjectName']);

        // Assert: the answer options are included.
        self::assertContains(
            '4',
            array_column($questions[0]['answers'], 'answerText')
        );
        self::assertContains(
            'Mars',
            array_column($questions[1]['answers'], 'answerText')
        );

        // Assert: the correct answers are returned.
        self::assertSame('4', $questions[0]['correctAnswer']);
        self::assertSame('Mars', $questions[1]['correctAnswer']);

        // Assert: percentage-correct values are returned as integers.
        self::assertSame(80, $questions[0]['percentageCorrect']);
        self::assertSame(65, $questions[1]['percentageCorrect']);
    }

    public function testReturnsAnEmptyArrayWhenThereAreNoQuestions(): void
    {
        // Arrange: create a subject but no questions.

        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName)
             VALUES ('subject-1', 'Mathematics')"
        );

        // Act: retrieve all questions.
        $questions = Question::getAllForLecturer();

        // Assert: no questions are returned.
        self::assertSame([], $questions);
    }
}
