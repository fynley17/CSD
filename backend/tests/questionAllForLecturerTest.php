
<?php

use App\Models\Model;
use App\Models\Question;
use PHPUnit\Framework\TestCase;

final class QuestionAllForLecturerTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        // Create an isolated in-memory SQLite database.
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec(
            'CREATE TABLE Subjects (
                subjectID TEXT PRIMARY KEY,
                subjectName TEXT NOT NULL,
                isActive INTEGER NOT NULL DEFAULT 1
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
            'CREATE TABLE StudentAnswers (
                studentAnswerID TEXT PRIMARY KEY,
                questionID TEXT NOT NULL
            )'
        );

        // Give the Question model the temporary database connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, $this->db);
    }

    protected function tearDown(): void
    {
        // Prevent other tests from reusing this connection.
        $dbProperty = new ReflectionProperty(Model::class, 'db');
        $dbProperty->setValue(null, null);
    }

    private function insertActiveSubject(): void
    {
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName, isActive)
             VALUES ('subject-1', 'Mathematics', 1)"
        );
    }

    private function insertQuestionWithAnswers(): void
    {
        $this->insertActiveSubject();

        $this->db->exec(
            "INSERT INTO Questions
                (questionID, subjectID, question)
             VALUES
                ('question-1', 'subject-1', 'What is 2 + 2?')"
        );

        $this->db->exec(
            "INSERT INTO Answers
                (answerID, questionID, answerText, isCorrect)
             VALUES
                ('answer-1', 'question-1', '3', 0),
                ('answer-2', 'question-1', '4', 1),
                ('answer-3', 'question-1', '5', 0),
                ('answer-4', 'question-1', '6', 0)"
        );

        // Add a historical student answer.
        $this->db->exec(
            "INSERT INTO StudentAnswers
                (studentAnswerID, questionID)
             VALUES
                ('student-answer-1', 'question-1')"
        );
    }

    private function validAnswers(): array
    {
        return [
            ['answerText' => 'Answer 1', 'isCorrect' => true],
            ['answerText' => 'Answer 2', 'isCorrect' => false],
            ['answerText' => 'Answer 3', 'isCorrect' => false],
            ['answerText' => 'Answer 4', 'isCorrect' => false],
        ];
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

    public function testRejectsQuestionsWithFewerThanFourAnswers(): void
    {
        $this->insertActiveSubject();

        $answers = [
            ['answerText' => 'Answer 1', 'isCorrect' => true],
            ['answerText' => 'Answer 2', 'isCorrect' => false],
            ['answerText' => 'Answer 3', 'isCorrect' => false],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'A question must have exactly four answers.'
        );

        Question::create('subject-1', 'Test question?', $answers);
    }

    public function testRejectsQuestionsWithNoCorrectAnswer(): void
    {
        $this->insertActiveSubject();

        $answers = [
            ['answerText' => 'Answer 1', 'isCorrect' => false],
            ['answerText' => 'Answer 2', 'isCorrect' => false],
            ['answerText' => 'Answer 3', 'isCorrect' => false],
            ['answerText' => 'Answer 4', 'isCorrect' => false],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Exactly one answer must be marked correct.'
        );

        Question::create('subject-1', 'Test question?', $answers);
    }

    public function testRejectsQuestionsWithMultipleCorrectAnswers(): void
    {
        $this->insertActiveSubject();

        $answers = [
            ['answerText' => 'Answer 1', 'isCorrect' => true],
            ['answerText' => 'Answer 2', 'isCorrect' => true],
            ['answerText' => 'Answer 3', 'isCorrect' => false],
            ['answerText' => 'Answer 4', 'isCorrect' => false],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Exactly one answer must be marked correct.'
        );

        Question::create('subject-1', 'Test question?', $answers);
    }

    public function testRejectsQuestionsForInactiveSubjects(): void
    {
        $this->db->exec(
            "INSERT INTO Subjects (subjectID, subjectName, isActive)
             VALUES ('subject-1', 'Mathematics', 0)"
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The selected subject is invalid or inactive.'
        );

        Question::create(
            'subject-1',
            'Test question?',
            $this->validAnswers()
        );
    }

    public function testPreventsEditingQuestionsWithStudentAnswers(): void
    {
        $this->insertQuestionWithAnswers();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'This question cannot be edited because it has already been used in a completed test.'
        );

        Question::update(
            'question-1',
            'subject-1',
            'Updated question?',
            $this->validAnswers()
        );
    }

    public function testPreventsDeletingQuestionsWithStudentAnswers(): void
    {
        $this->insertQuestionWithAnswers();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'This question cannot be deleted because it has already been used in a completed test.'
        );

        Question::delete('question-1');
    }
}
