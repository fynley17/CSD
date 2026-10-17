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

        // assign variables (after validation)
        $firstName     = $data['firstName'];
        $lastName      = $data['lastName'];
        $email         = $data['email'];
        $studentNumber = $data['studentNumber'];
        $passwordHash  = password_hash($data['password'], PASSWORD_DEFAULT);
        $cohortName    = $data['cohortName'] ?? '';
        $accountType   = 'student';
        $approvalStatus = 'pending';
        $points        = 0;

        $db = self::getDB();

        // Read-only checks first, no transaction needed
        $stmt = $db->prepare("SELECT 1 FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Email already exists');
        }

        $stmt = $db->prepare("SELECT 1 FROM Users WHERE username = ?");
        $stmt->execute([$studentNumber]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Student number already exists');
        }

        $stmt = $db->prepare("SELECT cohortID FROM Cohorts WHERE cohortName = ?");
        $stmt->execute([$cohortName]);
        $cohortID = $stmt->fetchColumn();
        if ($cohortID === false) {
            throw new \InvalidArgumentException('Cohort not found');
        }

        // Writes inside the transaction
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO Users (email, username, password, accountType, approvalStatus) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$email, $studentNumber, $passwordHash, $accountType, $approvalStatus]);

            $userID = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO Students (userID, firstName, lastName, cohortID, points) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userID, $firstName, $lastName, $cohortID, $points]);

            $db->commit();
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // 23000 = integrity constraint violation (e.g. duplicate key from a race)
            if ($e->getCode() === '23000') {
                throw new \InvalidArgumentException('Email or student number already exists');
            }
            throw new \RuntimeException('Database error: ' . $e->getMessage());
        }
    }

    // Helpers
    public static function names($name)
    {
        return preg_match("/^[a-zA-Z\s'-]{2,50}$/", $name);
    }

    public static function validUsername($studentNumber)
    {
        return preg_match("/^WS[0-9]{6}$/D", $studentNumber);
    }

    public static function validPassword($password)
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^a-zA-Z0-9\s]/', $password);
    }

    private static function ValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
