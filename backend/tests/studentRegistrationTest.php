<?php

use App\Models\Student;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StudentRegistrationTest extends TestCase
{
    // Keep server-side registration validation covered independently of browser validation.
    public function testNameValidation(): void
    {
        // Arrange: nothing to set up, the input is passed straight in

        // Act
        $validName = Student::names('Fyn Geary');
        $tooShort = Student::names('A');
        $hasCode = Student::names('<script>alert(1)</script>');
        $notText = Student::names(['Fyn']);

        // Assert
        self::assertTrue($validName);
        self::assertFalse($tooShort);
        self::assertFalse($hasCode);
        self::assertFalse($notText);
    }

    public function testStudentNumberValidation(): void
    {
        // Arrange: nothing to set up, the input is passed straight in

        // Act
        $validNumber = Student::validUsername('ws381211');
        $tooShort = Student::validUsername('ws38121');
        $trailingSpace = Student::validUsername('ws381211 ');
        $notText = Student::validUsername(['ws381211']);

        // Assert
        self::assertTrue($validNumber);
        self::assertFalse($tooShort);
        self::assertFalse($trailingSpace);
        self::assertFalse($notText);
    }

    public function testPasswordValidation(): void
    {
        // Arrange: nothing to set up, the input is passed straight in

        // Act
        $validPassword = Student::validPassword('Febmar17!');
        $noUppercaseOrSymbol = Student::validPassword('febmar17');
        $noNumber = Student::validPassword('Febmar!!');
        $notText = Student::validPassword(['Febmar17!']);

        // Assert
        self::assertTrue($validPassword);
        self::assertFalse($noUppercaseOrSymbol);
        self::assertFalse($noNumber);
        self::assertFalse($notText);
    }

    #[DataProvider('invalidRegistrationCases')]
    public function testRegistrationRejectsInvalidInput(array $data, string $expectedMessage): void
    {
        // Arrange: $data and $expectedMessage come from invalidRegistrationCases()

        // Assert: PHPUnit has to be told which exception to expect before the Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        // Act
        Student::register($data);
    }

    public static function invalidRegistrationCases(): array
    {
        $validData = [
            'firstName' => 'Fyn',
            'lastName' => 'Geary',
            'email' => 'fyn@example.test',
            'studentNumber' => 'ws381211',
            'password' => 'Febmar17!',
            'confirmPassword' => 'Febmar17!',
            'cohortID' => 'df7c0bc6-ca26-11f1-8d08-06694864b0fb',
        ];

        return [
            'missing first name' => [
                array_replace($validData, ['firstName' => '']),
                'Please enter a valid first name.',
            ],
            'invalid first name' => [
                array_replace($validData, ['firstName' => '<script>']),
                'Please enter a valid first name.',
            ],
            'non-string first name' => [
                array_replace($validData, ['firstName' => ['Fyn']]),
                'Please enter a valid first name.',
            ],
            'invalid last name' => [
                array_replace($validData, ['lastName' => 'Geary1']),
                'Please enter a valid last name.',
            ],
            'invalid email' => [
                array_replace($validData, ['email' => 'not-an-email']),
                'Please enter a valid email address.',
            ],
            'non-string email' => [
                array_replace($validData, ['email' => ['fyn@example.test']]),
                'Please enter a valid email address.',
            ],
            'invalid student number' => [
                array_replace($validData, ['studentNumber' => 'ws38121']),
                'Please enter a valid student number, for example ws123456.',
            ],
            'non-string student number' => [
                array_replace($validData, ['studentNumber' => ['ws381211']]),
                'Please enter a valid student number, for example ws123456.',
            ],
            'weak password' => [
                array_replace($validData, ['password' => 'weak']),
                'Please use at least 8 characters, including an uppercase letter, a lowercase letter, a number, '
                . 'and a symbol.',
            ],
            'non-string password' => [
                array_replace($validData, ['password' => ['Febmar17!']]),
                'Please use at least 8 characters, including an uppercase letter, a lowercase letter, a number, '
                . 'and a symbol.',
            ],
            'password mismatch' => [
                array_replace($validData, ['confirmPassword' => 'Different17!']),
                'Passwords do not match.',
            ],
            'missing cohort' => [
                array_replace($validData, ['cohortID' => '']),
                'Please select a cohort.',
            ],
            'non-string cohort' => [
                array_replace($validData, ['cohortID' => ['df7c0bc6-ca26-11f1-8d08-06694864b0fb']]),
                'Please select a cohort.',
            ],
        ];
    }
}
