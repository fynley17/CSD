<?php

use App\Models\Student;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StudentRegistrationTest extends TestCase
{
    public function testNameValidation(): void
    {
        self::assertTrue(Student::names('Fyn Geary'));
        self::assertFalse(Student::names('A'));
        self::assertFalse(Student::names('<script>alert(1)</script>'));
        self::assertFalse(Student::names(['Fyn']));
    }

    public function testStudentNumberValidation(): void
    {
        self::assertTrue(Student::validUsername('ws381211'));
        self::assertFalse(Student::validUsername('ws38121'));
        self::assertFalse(Student::validUsername('ws381211 '));
        self::assertFalse(Student::validUsername(['ws381211']));
    }

    public function testPasswordValidation(): void
    {
        self::assertTrue(Student::validPassword('Febmar17!'));
        self::assertFalse(Student::validPassword('febmar17'));
        self::assertFalse(Student::validPassword('Febmar!!'));
        self::assertFalse(Student::validPassword(['Febmar17!']));
    }

    #[DataProvider('invalidRegistrationCases')]
    public function testRegistrationRejectsInvalidInput(array $data, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

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
            'missing first name' => [array_replace($validData, ['firstName' => '']), 'not valid first name'],
            'invalid first name' => [array_replace($validData, ['firstName' => '<script>']), 'not valid first name'],
            'non-string first name' => [array_replace($validData, ['firstName' => ['Fyn']]), 'not valid first name'],
            'invalid last name' => [array_replace($validData, ['lastName' => 'Geary1']), 'not valid last name'],
            'invalid email' => [array_replace($validData, ['email' => 'not-an-email']), 'not valid email'],
            'non-string email' => [array_replace($validData, ['email' => ['fyn@example.test']]), 'not valid email'],
            'invalid student number' => [array_replace($validData, ['studentNumber' => 'ws38121']), 'not valid student number'],
            'non-string student number' => [array_replace($validData, ['studentNumber' => ['ws381211']]), 'not valid student number'],
            'weak password' => [array_replace($validData, ['password' => 'weak']), 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a symbol'],
            'non-string password' => [array_replace($validData, ['password' => ['Febmar17!']]), 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a symbol'],
            'password mismatch' => [array_replace($validData, ['confirmPassword' => 'Different17!']), 'Passwords do not match'],
            'missing cohort' => [array_replace($validData, ['cohortID' => '']), 'Please select a cohort'],
            'non-string cohort' => [array_replace($validData, ['cohortID' => ['df7c0bc6-ca26-11f1-8d08-06694864b0fb']]), 'Please select a cohort'],
        ];
    }
}
