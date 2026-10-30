<?php

namespace App\Models;

class Student extends User
{
    public static function getAll(): array
    {
        $db = self::getDB();

        $subjectSql = $db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "
                SELECT GROUP_CONCAT(subjectName, ', ')
                FROM (
                    SELECT DISTINCT sub.subjectName
                    FROM Subjects AS sub
                    WHERE sub.cohortID = s.cohortID
                    ORDER BY sub.subjectName
                )
            "
            : "
                SELECT GROUP_CONCAT(
                    DISTINCT sub.subjectName
                    ORDER BY sub.subjectName
                    SEPARATOR ', '
                )
                FROM Subjects AS sub
                WHERE sub.cohortID = s.cohortID
            ";

        $sql = "
            SELECT
                s.userID,
                TRIM(COALESCE(s.firstName, '') || ' ' || COALESCE(s.lastName, '')) AS name,
                u.username AS studentNumber,
                u.email,
                CASE
                    WHEN u.isEnabled = 0 THEN 'Disabled'
                    WHEN u.approvalStatus = 'pending' THEN 'Pending'
                    ELSE 'Active'
                END AS status,
                u.lastLogin,
                c.cohortName AS cohort,
                ($subjectSql) AS subjects
            FROM Students AS s
            INNER JOIN Users AS u ON u.userID = s.userID
            LEFT JOIN Cohorts AS c ON c.cohortID = s.cohortID
            WHERE u.accountType = 'student'
            ORDER BY s.lastName ASC, s.firstName ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function register($data)
    {
        // Validate every submitted value on the server; browser-side checks are only a convenience.
        // Validate and sanitise input data.
        if (empty($data['firstName']) || !self::names($data['firstName'])) {
            throw new \InvalidArgumentException('Please enter a valid first name.');
        }
        if (empty($data['lastName']) || !self::names($data['lastName'])) {
            throw new \InvalidArgumentException('Please enter a valid last name.');
        }
        if (empty($data['email']) || !self::validEmail($data['email'])) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }
        if (empty($data['studentNumber']) || !self::validUsername($data['studentNumber'])) {
            throw new \InvalidArgumentException('Please enter a valid student number, for example ws123456.');
        }
        if (empty($data['password']) || !self::validPassword($data['password'])) {
            throw new \InvalidArgumentException(
                'Please use at least 8 characters, including an uppercase letter, a lowercase letter, a number, '
                . 'and a symbol.'
            );
        }
        if (empty($data['confirmPassword']) || $data['password'] !== $data['confirmPassword']) {
            throw new \InvalidArgumentException('Passwords do not match.');
        }
        if (empty($data['cohortID'])) {
            throw new \InvalidArgumentException('Please select a cohort.');
        }
        if (!is_string($data['cohortID'])) {
            throw new \InvalidArgumentException('Please select a cohort.');
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

        // Check account uniqueness and confirm the selected cohort before writing.
        $stmt = $db->prepare("SELECT 1 FROM Users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('An account with this email address already exists.');
        }

        $stmt = $db->prepare("SELECT 1 FROM Users WHERE username = ? LIMIT 1");
        $stmt->execute([$studentNumber]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('An account with this student number already exists.');
        }

        // The cohort name is also needed for the welcome email.
        $stmt = $db->prepare("SELECT cohortID, cohortName FROM Cohorts WHERE cohortID = ? LIMIT 1");
        $stmt->execute([$cohortID]);
        $cohort = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($cohort === false) {
            throw new \InvalidArgumentException(
                'The selected cohort was not found. Please reload the page and select an available cohort.'
            );
        }
        $cohortID = $cohort['cohortID'];

        $userID = $db->query("SELECT UUID()")->fetchColumn();
        if (!is_string($userID) || $userID === '') {
            throw new \RuntimeException('Unable to generate user ID.');
        }

        // Keep the user and student records atomic so neither can be created on its own.
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO Users (userID, email, username, password, accountType, approvalStatus, isEmailVerified) "
                . "VALUES (?, ?, ?, ?, ?, ?, 0)"
            );
            $stmt->execute([$userID, $email, $studentNumber, $passwordHash, $accountType, $approvalStatus]);

            $stmt = $db->prepare(
                "INSERT INTO Students (userID, firstName, lastName, cohortID, points) "
                . "VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userID, $firstName, $lastName, $cohortID, $points]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Hand back what the controller needs to send the verification email.
        return [
            'success' => true,
            'userID' => $userID,
            'email' => $email,
        ];
    }

    /**
     * Finds the details the verification and welcome emails need for one student.
     *
     * @return array|null null if there's no student with that ID
     */
    public static function findVerificationDetails(string $userID): ?array
    {
        $stmt = self::getDB()->prepare(
            "SELECT Users.email, Users.isEmailVerified, Students.firstName, Students.lastName, Cohorts.cohortName "
            . "FROM Users "
            . "JOIN Students ON Students.userID = Users.userID "
            . "JOIN Cohorts ON Cohorts.cohortID = Students.cohortID "
            . "WHERE Users.userID = ?"
        );
        $stmt->execute([$userID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['isEmailVerified'] = (bool) $row['isEmailVerified'];
        return $row;
    }

    /**
     * Changes the email address on an account that hasn't been verified yet, i.e. to fix a typo.
     * Verified accounts are left alone, so this can't be used to take over someone's account.
     *
     * @return string the cleaned email address that was saved
     */
    public static function changeEmail(string $userID, mixed $email): string
    {
        // a space copied in by accident shouldn't stop the change
        $email = is_string($email) ? strtolower(trim($email)) : $email;

        if (!self::validEmail($email)) {
            throw new \InvalidArgumentException('Enter an email address in the correct format, like name@example.com');
        }
        $db = self::getDB();

        // another account already using it, the student's own account is skipped so re-entering it is fine
        $stmt = $db->prepare("SELECT 1 FROM Users WHERE email = ? AND userID <> ? LIMIT 1");
        $stmt->execute([$email, $userID]);
        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('An account with this email address already exists');
        }

        $stmt = $db->prepare("UPDATE Users SET email = ? WHERE userID = ? AND isEmailVerified = 0");
        $stmt->execute([$email, $userID]);

        return $email;
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

    /**
     * Gets the five most recently logged-in students for the dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRecentActivity(): array
    {
        // Arrange: select student details and their latest login information.
        $sql = "
            SELECT
                CONCAT_WS(' ', s.firstName, s.lastName) AS name,
                u.username AS studentNumber,
                u.email,
                CASE
                    WHEN u.isEnabled = 0 THEN 'Disabled'
                    WHEN u.approvalStatus = 'pending' THEN 'Pending approval'
                    ELSE 'Active'
                END AS status,
                u.lastLogin
            FROM Students AS s
            INNER JOIN Users AS u ON u.userID = s.userID
            WHERE u.accountType = 'student'
            AND u.lastLogin IS NOT NULL
            ORDER BY u.lastLogin DESC
            LIMIT 5
        ";

        // Act: execute the query using the shared database connection.
        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        // Assert/return: provide the results to the controller.
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function validEmail($email)
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
