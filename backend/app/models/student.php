<?php

namespace App\Models;

class Student extends Model
{
    public static function getAll()
    {
        $stmt = self::getDB()->prepare("SELECT * FROM Students");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function register($data)
    {

        // Validate & sanitize input data
        if (empty($data['firstName']) || !self::names($data['firstName'])) {
            throw new \InvalidArgumentException('not valid first name');
        }
        if (empty($data['lastName']) || !self::names($data['lastName'])) {
            throw new \InvalidArgumentException('not valid last name');
        }
        if (empty($data['email']) || !self::ValidEmail($data['email'])) {
            throw new \InvalidArgumentException('not valid email');
        }
        if (empty($data['studentNumber']) || !self::validUsername($data['studentNumber'])) {
            throw new \InvalidArgumentException('not valid student number');
        }
        if (empty($data['password']) || !self::validPassword($data['password'])) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a symbol');
        }
        if (empty($data['confirmPassword']) || $data['password'] !== $data['confirmPassword']) {
            throw new \InvalidArgumentException('Passwords do not match');
        }
        if (empty($data['cohortID'])) {
            throw new \InvalidArgumentException('Please select a cohort');
        }
        if (!is_string($data['cohortID'])) {
            throw new \InvalidArgumentException('Please select a cohort');
        }

        // assign variables (after validation)
        $firstName     = $data['firstName'];
        $lastName      = $data['lastName'];
        $email         = strtolower($data['email']);
        $studentNumber = strtolower($data['studentNumber']);
        $passwordHash  = password_hash($data['password'], PASSWORD_DEFAULT);
        $cohortID    = $data['cohortID'];
        $accountType   = 'student';
        $approvalStatus = 'pending';
        $points        = 0;

        $db = self::getDB();

        // Read-only checks first, no transaction needed
        $stmt = $db->prepare("SELECT 1 FROM Users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Email already exists');
        }

        $stmt = $db->prepare("SELECT 1 FROM Users WHERE username = ? LIMIT 1");
        $stmt->execute([$studentNumber]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Student number already exists');
        }

        $stmt = $db->prepare("SELECT cohortID FROM Cohorts WHERE cohortID = ? LIMIT 1");
        $stmt->execute([$cohortID]);
        $cohortID = $stmt->fetchColumn();
        if ($cohortID === false) {
            throw new \InvalidArgumentException('Selected cohort was not found. Please reload the page and select an available cohort.');
        }

        $userID = $db->query("SELECT UUID()")->fetchColumn();
        if (!is_string($userID) || $userID === '') {
            throw new \RuntimeException('Unable to generate user ID');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO Users (userID, email, username, password, accountType, approvalStatus) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userID, $email, $studentNumber, $passwordHash, $accountType, $approvalStatus]);

            $stmt = $db->prepare("INSERT INTO Students (userID, firstName, lastName, cohortID, points) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userID, $firstName, $lastName, $cohortID, $points]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['success' => true];
    }

    // Helpers
    public static function names($name)
    {
        return is_string($name) && preg_match("/^[a-zA-Z\s'-]{2,50}$/", $name) === 1;
    }

    public static function validUsername($studentNumber)
    {
        return is_string($studentNumber) && preg_match("/^ws[0-9]{6}$/D", $studentNumber) === 1;
    }

    public static function validPassword($password)
    {
        return is_string($password)
            && strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9\s]/', $password) === 1;
    }

    private static function ValidEmail($email)
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
