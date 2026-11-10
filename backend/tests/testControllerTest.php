<?php

use App\Controllers\TestController;
use PHPUnit\Framework\TestCase;

class TestControllerTest extends TestCase
{
    public function testValidTestRequestIsAccepted(): void
    {
        $data = [
            'testName' => '  Biology Test  ',
            'subjectID' => 'subject-123',
            'testDescription' => '  Biology assessment  ',
            'timeLimit' => '45',
            'dueDate' => '2026-12-15',
            'questionCount' => '10'
        ];

        $result = TestController::validateTestRequestData($data);

        $this->assertSame([
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'testDescription' => '  Biology assessment  ',
            'timeLimit' => 45,
            'dueDate' => '2026-12-15',
            'questionCount' => 10,
            'questionSelectionMethod' => 'random'
        ], $result);
    }

    public function testOptionalFieldsCanBeOmitted(): void
    {
        $data = [
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'questionCount' => 10
        ];

        $result = TestController::validateTestRequestData($data);

        $this->assertNull($result['testDescription']);
        $this->assertNull($result['timeLimit']);
        $this->assertNull($result['dueDate']);
    }

    public function testQuestionSelectionMethodDefaultsToRandom(): void
    {
        $data = [
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'questionCount' => 10
        ];

        $result = TestController::validateTestRequestData($data);

        $this->assertSame('random', $result['questionSelectionMethod']);
    }

    public function testFrequentlyIncorrectSelectionMethodIsAccepted(): void
    {
        $data = [
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'questionCount' => 10,
            'questionSelectionMethod' => 'frequently_incorrect'
        ];

        $result = TestController::validateTestRequestData($data);

        $this->assertSame(
            'frequently_incorrect',
            $result['questionSelectionMethod']
        );
    }

    public function testInvalidQuestionSelectionMethodIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Please select a valid question selection method.'
        );

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'questionCount' => 10,
            'questionSelectionMethod' => 'invalid_method'
        ]);
    }

    public function testEmptyTimeLimitIsAcceptedAsNull(): void
    {
        $data = [
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'timeLimit' => '',
            'questionCount' => 10
        ];

        $result = TestController::validateTestRequestData($data);

        $this->assertNull($result['timeLimit']);
    }

    public function testTestNameCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Test name cannot be empty.');

        TestController::validateTestRequestData([
            'testName' => '   ',
            'subjectID' => 'subject-123',
            'questionCount' => 10
        ]);
    }

    public function testTestNameMustBeAString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Test name cannot be empty.');

        TestController::validateTestRequestData([
            'testName' => 123,
            'subjectID' => 'subject-123',
            'questionCount' => 10
        ]);
    }

    public function testSubjectMustBeProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A valid subject must be selected.');

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'questionCount' => 10
        ]);
    }

    public function testSubjectCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A valid subject must be selected.');

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'subjectID' => ' ',
            'questionCount' => 10
        ]);
    }

    public function testDescriptionMustBeText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Test description must be text.');

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'testDescription' => 123,
            'questionCount' => 10
        ]);
    }

    public function testTimeLimitMustBeAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Time limit must be a valid number.');

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'timeLimit' => 'abc',
            'questionCount' => 10
        ]);
    }

    public function testDueDateMustBeAString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Due date must be a valid date.');

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'dueDate' => 123,
            'questionCount' => 10
        ]);
    }

    public function testQuestionCountIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question count must be a valid number.');

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123'
        ]);
    }

    public function testQuestionCountMustBeAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question count must be a valid number.');

        TestController::validateTestRequestData([
            'testName' => 'Biology Test',
            'subjectID' => 'subject-123',
            'questionCount' => 'abc'
        ]);
    }
}
