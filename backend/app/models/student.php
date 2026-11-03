<?php

namespace App\Models;

class Student extends User
{
    public static function getAll(): array
    {
        $db = self::getDB();
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Use the correct name concatenation syntax for the active database.
        $nameSql = $driver === 'sqlite'
            ? "TRIM(COALESCE(s.firstName, '') || ' ' || COALESCE(s.lastName, ''))"
            : "CONCAT_WS(' ', s.firstName, s.lastName)";

        // SQLite and MySQL use different GROUP_CONCAT syntax.
        $subjectSql = $driver === 'sqlite'
            ? "
                SELECT GROUP_CONCAT(subjectName, ', ')
                FROM (
                    SELECT DISTINCT sub.subjectName
                    FROM Subjects AS sub
                    WHERE sub.cohortID = s.cohortID
                    ORDER BY sub.subjectName
                ) AS distinct_subjects
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
                s.firstName,
                s.lastName,
                s.cohortID,
                $nameSql AS name,
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

    /**
     * Creates an active student account on behalf of a lecturer.
     * Generates a temporary password and stores only its secure hash.
     *
     * @return array<string, mixed>
     */
    public static function createByLecturer(array $data): array
    {
        if (empty($data['firstName']) || !self::names(trim($data['firstName']))) {
            throw new \InvalidArgumentException('Please enter a valid first name.');
        }

        if (empty($data['lastName']) || !self::names(trim($data['lastName']))) {
            throw new \InvalidArgumentException('Please enter a valid last name.');
        }

        if (empty($data['email']) || !self::validEmail(trim($data['email']))) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }

        if (
            empty($data['studentNumber'])
            || !self::validUsername(strtolower(trim($data['studentNumber'])))
        ) {
            throw new \InvalidArgumentException(
                'Please enter a valid student number, for example ws123456.'
            );
        }

        if (empty($data['cohortID']) || !is_string($data['cohortID'])) {
            throw new \InvalidArgumentException('Please select a cohort.');
        }

        $firstName = trim($data['firstName']);
        $lastName = trim($data['lastName']);
        $email = strtolower(trim($data['email']));
        $studentNumber = strtolower(trim($data['studentNumber']));
        $cohortID = trim($data['cohortID']);

        $db = self::getDB();

        // Check whether the email address is already registered.
        $stmt = $db->prepare(
            'SELECT 1 FROM Users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException(
                'An account with this email address already exists.'
            );
        }

        // Check whether the student number is already registered.
        $stmt = $db->prepare(
            'SELECT 1 FROM Users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$studentNumber]);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException(
                'An account with this student number already exists.'
            );
        }

        // Confirm that the selected cohort exists.
        $stmt = $db->prepare(
            'SELECT cohortID FROM Cohorts WHERE cohortID = ? LIMIT 1'
        );
        $stmt->execute([$cohortID]);

        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException(
                'The selected cohort could not be found.'
            );
        }

        // Generate a random 10-character temporary password.
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $temporaryPassword = '';

        for ($i = 0; $i < 10; $i++) {
            $temporaryPassword .= $characters[
                random_int(0, strlen($characters) - 1)
            ];
        }

        // Hash the password before storing it in the database.
        $passwordHash = password_hash(
            $temporaryPassword,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new \RuntimeException(
                'Unable to secure the temporary password.'
            );
        }

        // Generate a UUID for the new student.
        $userID = $db->query('SELECT UUID()')->fetchColumn();

        if (!is_string($userID) || $userID === '') {
            throw new \RuntimeException('Unable to generate user ID.');
        }

        // Create the user and student records together.
        $db->beginTransaction();

        try {
            // Lecturer-created accounts are immediately active.
            $stmt = $db->prepare(
                "INSERT INTO Users (
                    userID,
                    username,
                    email,
                    password,
                    accountType,
                    approvalStatus,
                    isEnabled,
                    isEmailVerified
                ) VALUES (?, ?, ?, ?, 'student', 'approved', 1, 1)"
            );

            $stmt->execute([
                $userID,
                $studentNumber,
                $email,
                $passwordHash,
            ]);

            $stmt = $db->prepare(
                'INSERT INTO Students (
                    userID,
                    firstName,
                    lastName,
                    cohortID,
                    points
                ) VALUES (?, ?, ?, ?, 0)'
            );

            $stmt->execute([
                $userID,
                $firstName,
                $lastName,
                $cohortID,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        return [
            'success' => true,
            'userID' => $userID,
            'username' => $studentNumber,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'temporaryPassword' => $temporaryPassword,
        ];
    }

    /**
     * Enables or disables a student account.
     * Returns false only when the student does not exist.
     */
    public static function setEnabled(string $userID, bool $enabled): bool
    {
        $db = self::getDB();
        $stmt = $db->prepare(
            "UPDATE Users SET isEnabled = ? WHERE userID = ? AND accountType = 'student'"
        );
        $stmt->execute([$enabled ? 1 : 0, $userID]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $check = $db->prepare(
            "SELECT 1 FROM Users WHERE userID = ? AND accountType = 'student' LIMIT 1"
        );
        $check->execute([$userID]);
        return $check->fetchColumn() !== false;
    }

    /** Retrieves the details needed to email a student awaiting approval. */
    public static function getPendingApprovalDetails(string $userID): ?array
    {
        $stmt = self::getDB()->prepare(
            "SELECT s.firstName, u.email
            FROM Students AS s
            INNER JOIN Users AS u ON u.userID = s.userID
            WHERE s.userID = ?
            AND u.accountType = 'student'
            AND u.approvalStatus = 'pending'
            LIMIT 1"
        );

        $stmt->execute([$userID]);
        $student = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $student ?: null;
    }

    /** Approves an application only while it is pending. */
    public static function approveApplication(string $userID): bool
    {
        $stmt = self::getDB()->prepare(
            "UPDATE Users
             SET approvalStatus = 'approved', isEnabled = 1
             WHERE userID = ? AND accountType = 'student' AND approvalStatus = 'pending'"
        );
        $stmt->execute([$userID]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Declines a pending application by deleting its student account.
     * The database foreign keys are configured to cascade dependent student records.
     */
    public static function declineApplication(string $userID): bool
    {
        $db = self::getDB();
        $stmt = $db->prepare(
            "DELETE FROM Users
             WHERE userID = ? AND accountType = 'student' AND approvalStatus = 'pending'"
        );
        $stmt->execute([$userID]);
        return $stmt->rowCount() > 0;
    }

    /** Updates the editable details for an existing student. */
    public static function updateDetails(string $userID, array $data): void
    {
        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $studentNumber = strtolower(trim((string) ($data['studentNumber'] ?? '')));
        $cohortID = trim((string) ($data['cohortID'] ?? ''));

        if (!self::names($firstName)) {
            throw new \InvalidArgumentException('Please enter a valid first name.');
        }
        if (!self::names($lastName)) {
            throw new \InvalidArgumentException('Please enter a valid last name.');
        }
        if (!self::validEmail($email)) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }
        if (!self::validUsername($studentNumber)) {
            throw new \InvalidArgumentException('Please enter a valid student number, for example ws123456.');
        }
        if ($cohortID === '') {
            throw new \InvalidArgumentException('Please select a cohort.');
        }

        $db = self::getDB();
        $studentCheck = $db->prepare(
            "SELECT 1 FROM Students s
             INNER JOIN Users u ON u.userID = s.userID
             WHERE s.userID = ? AND u.accountType = 'student' LIMIT 1"
        );
        $studentCheck->execute([$userID]);
        if ($studentCheck->fetchColumn() === false) {
            throw new \OutOfBoundsException('Student account not found.');
        }

        $duplicate = $db->prepare(
            'SELECT 1 FROM Users WHERE (email = ? OR username = ?) AND userID <> ? LIMIT 1'
        );
        $duplicate->execute([$email, $studentNumber, $userID]);
        if ($duplicate->fetchColumn() !== false) {
            throw new \InvalidArgumentException(
                'Another account already uses this email address or student number.'
            );
        }

        $cohortCheck = $db->prepare('SELECT 1 FROM Cohorts WHERE cohortID = ? LIMIT 1');
        $cohortCheck->execute([$cohortID]);
        if ($cohortCheck->fetchColumn() === false) {
            throw new \InvalidArgumentException('The selected cohort could not be found.');
        }

        $db->beginTransaction();
        try {
            $userUpdate = $db->prepare(
                'UPDATE Users SET email = ?, username = ? WHERE userID = ? AND accountType = \'student\''
            );
            $userUpdate->execute([$email, $studentNumber, $userID]);

            $studentUpdate = $db->prepare(
                'UPDATE Students SET firstName = ?, lastName = ?, cohortID = ? WHERE userID = ?'
            );
            $studentUpdate->execute([$firstName, $lastName, $cohortID, $userID]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Permanently deletes a student account. */
    public static function deleteStudent(string $userID): bool
    {
        $stmt = self::getDB()->prepare(
            "DELETE FROM Users WHERE userID = ? AND accountType = 'student'"
        );
        $stmt->execute([$userID]);
        return $stmt->rowCount() > 0;
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
            throw new \InvalidArgumentException(
                'Please enter a valid student number, for example ws123456.'
            );
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

        // Assign variables after validation.
        $firstName = $data['firstName'];
        $lastName = $data['lastName'];
        $email = strtolower($data['email']);
        $studentNumber = strtolower($data['studentNumber']);
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $cohortID = $data['cohortID'];
        $accountType = 'student';
        $approvalStatus = 'pending';
        $points = 0;

        $db = self::getDB();

        // Check account uniqueness and confirm the selected cohort before writing.
        $stmt = $db->prepare(
            'SELECT 1 FROM Users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException(
                'An account with this email address already exists.'
            );
        }

        $stmt = $db->prepare(
            'SELECT 1 FROM Users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$studentNumber]);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException(
                'An account with this student number already exists.'
            );
        }

        // The cohort name is also needed for the welcome email.
        $stmt = $db->prepare(
            'SELECT cohortID, cohortName FROM Cohorts WHERE cohortID = ? LIMIT 1'
        );
        $stmt->execute([$cohortID]);
        $cohort = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($cohort === false) {
            throw new \InvalidArgumentException(
                'The selected cohort was not found. Please reload the page and select an available cohort.'
            );
        }

        $cohortID = $cohort['cohortID'];

        $userID = $db->query('SELECT UUID()')->fetchColumn();

        if (!is_string($userID) || $userID === '') {
            throw new \RuntimeException('Unable to generate user ID.');
        }

        // Keep the user and student records atomic so neither can be created on its own.
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO Users (userID, email, username, password, accountType, approvalStatus, isEmailVerified) '
                . 'VALUES (?, ?, ?, ?, ?, ?, 0)'
            );

            $stmt->execute([
                $userID,
                $email,
                $studentNumber,
                $passwordHash,
                $accountType,
                $approvalStatus,
            ]);

            $stmt = $db->prepare(
                'INSERT INTO Students (userID, firstName, lastName, cohortID, points) '
                . 'VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $userID,
                $firstName,
                $lastName,
                $cohortID,
                $points,
            ]);

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
            'SELECT Users.email, Users.isEmailVerified, Students.firstName, Students.lastName, Cohorts.cohortName '
            . 'FROM Users '
            . 'JOIN Students ON Students.userID = Users.userID '
            . 'JOIN Cohorts ON Cohorts.cohortID = Students.cohortID '
            . 'WHERE Users.userID = ?'
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
        // A space copied in by accident shouldn't stop the change.
        $email = is_string($email) ? strtolower(trim($email)) : $email;

        if (!self::validEmail($email)) {
            throw new \InvalidArgumentException(
                'Enter an email address in the correct format, like name@example.com'
            );
        }

        $db = self::getDB();

        // Another account already using it, the student's own account is skipped so re-entering it is fine.
        $stmt = $db->prepare(
            'SELECT 1 FROM Users WHERE email = ? AND userID <> ? LIMIT 1'
        );
        $stmt->execute([$email, $userID]);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException(
                'An account with this email address already exists'
            );
        }

        $stmt = $db->prepare(
            'UPDATE Users SET email = ? WHERE userID = ? AND isEmailVerified = 0'
        );
        $stmt->execute([$email, $userID]);

        return $email;
    }

    // Helpers
    public static function names($name)
    {
        return is_string($name)
            && preg_match("/^[a-zA-Z\s'-]{2,50}$/", $name) === 1;
    }

    public static function validUsername($studentNumber)
    {
        return is_string($studentNumber)
            && preg_match('/^ws[0-9]{6}$/D', $studentNumber) === 1;
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
        return is_string($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
