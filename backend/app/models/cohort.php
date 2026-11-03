<?php

namespace App\Models;

class Cohort extends Model
{
    private const DUPLICATE_NAME = 'A cohort with this name already exists';
    private const MAX_NAME_LENGTH = 50;

    public static function getAll()
    {
        // Return cohort identifiers and names for the registration form's selector, disabled cohorts can't be joined.
        $stmt = self::getDB()->prepare("SELECT cohortID, cohortName FROM Cohorts WHERE isActive = 1");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Every cohort A to Z with its students and subjects, for the cohort management page
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllForManagement(): array
    {
        $stmt = self::getDB()->prepare(
            "SELECT cohortID, cohortName, createdDate, isActive FROM Cohorts ORDER BY cohortName"
        );
        $stmt->execute();

        $students = self::getStudentsByCohort();
        $subjects = self::getSubjectsByCohort();

        $cohorts = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $cohortID = $row['cohortID'];
            $cohorts[] = [
                'cohortID' => $cohortID,
                'cohortName' => $row['cohortName'],
                'createdDate' => $row['createdDate'],
                'isActive' => (int) $row['isActive'] === 1,
                'students' => $students[$cohortID] ?? [],
                'subjects' => $subjects[$cohortID] ?? [],
            ];
        }

        return $cohorts;
    }

    /**
     * One cohort with its students and subjects, the same shape as getAllForManagement()
     *
     * @return array<string, mixed>|null null if there's no cohort with this ID
     */
    public static function findForManagement(string $cohortID): ?array
    {
        // the database's own copy of the ID, MariaDB also matches other spellings of a UUID, like uppercase
        $stmt = self::getDB()->prepare("SELECT cohortID FROM Cohorts WHERE cohortID = ? LIMIT 1");
        $stmt->execute([$cohortID]);
        $savedID = $stmt->fetchColumn();

        foreach (self::getAllForManagement() as $cohort) {
            if ($cohort['cohortID'] === $savedID) {
                return $cohort;
            }
        }

        return null;
    }

    /**
     * Saves whether a cohort is active, along with its students and subjects
     * Disabling disables its students and archives its subjects, enabling only undoes what disabling changed,
     * so a student disabled or a subject archived for another reason stays that way
     * Anything else that changes Users.isEnabled has to clear disabledByCohort, or enabling the cohort will undo it
     * Checks it exists first, as MariaDB counts no rows changed when the value is already the same
     *
     * @return bool false if there's no cohort with this ID
     */
    public static function setActive(string $cohortID, bool $isActive): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT 1 FROM Cohorts WHERE cohortID = ? LIMIT 1");
        $stmt->execute([$cohortID]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        // all or nothing, so a cohort can't end up disabled with students who can still log in
        $db->beginTransaction();
        try {
            self::applyStatus($cohortID, $isActive);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * The updates behind a status change, the caller wraps them in a transaction
     */
    private static function applyStatus(string $cohortID, bool $isActive): void
    {
        $db = self::getDB();

        $stmt = $db->prepare("UPDATE Cohorts SET isActive = ? WHERE cohortID = ?");
        $stmt->execute([$isActive ? 1 : 0, $cohortID]);

        $statements = $isActive
            ? [
                "UPDATE Users SET isEnabled = 1, disabledByCohort = 0 WHERE disabledByCohort = 1
                    AND userID IN (SELECT userID FROM Students WHERE cohortID = ?)",
                "UPDATE Subjects SET isActive = 1, archivedByCohort = 0
                    WHERE archivedByCohort = 1 AND cohortID = ?",
            ]
            : [
                "UPDATE Users SET isEnabled = 0, disabledByCohort = 1 WHERE isEnabled = 1
                    AND userID IN (SELECT userID FROM Students WHERE cohortID = ?)",
                "UPDATE Subjects SET isActive = 0, archivedByCohort = 1 WHERE isActive = 1 AND cohortID = ?",
            ];

        foreach ($statements as $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$cohortID]);
        }
    }

    /**
     * Tidies a cohort name and checks it can be used, extra spaces are removed first
     * Only letters, numbers, spaces and - ' & ( ) . , / are allowed, so a name can't contain HTML
     *
     * @return string the tidied name
     * @throws \InvalidArgumentException if the name is empty, too long or has other characters in it
     */
    public static function cleanName(mixed $cohortName): string
    {
        $cleanName = is_string($cohortName) ? trim((string) preg_replace('/\s+/u', ' ', $cohortName)) : '';

        if ($cleanName === '') {
            throw new \InvalidArgumentException('Enter a cohort name');
        }
        if (mb_strlen($cleanName) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException('Cohort name must be 50 characters or fewer');
        }
        if (preg_match("/^[\\p{L}\\p{N} \\-'&().,\\/]+$/uD", $cleanName) !== 1) {
            throw new \InvalidArgumentException(
                "Cohort name can only use letters, numbers, spaces and - ' & ( ) . , /"
            );
        }

        return $cleanName;
    }

    /**
     * Creates a cohort with today's date
     *
     * @return string the new cohort's ID
     * @throws \InvalidArgumentException if the name can't be used or is already taken
     */
    public static function create(mixed $cohortName, bool $isActive = true): string
    {
        $cleanName = self::cleanName($cohortName);
        $db = self::getDB();

        self::stopIfNameTaken($cleanName);

        try {
            $stmt = $db->prepare("INSERT INTO Cohorts (cohortName, isActive) VALUES (?, ?)");
            $stmt->execute([$cleanName, $isActive ? 1 : 0]);
        } catch (\PDOException $e) {
            self::stopIfDuplicateError($e);
            throw $e;
        }

        // the database makes the ID, so it's looked up by the name that's now unique
        $stmt = $db->prepare("SELECT cohortID FROM Cohorts WHERE cohortName = ? LIMIT 1");
        $stmt->execute([$cleanName]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * Saves a cohort's name and status together, changing only the upper and lower case of its own name is fine
     * A status change also updates its students and subjects, the same as setActive()
     *
     * @return bool false if there's no cohort with this ID
     * @throws \InvalidArgumentException if the name can't be used or another cohort has it
     */
    public static function update(string $cohortID, mixed $cohortName, bool $isActive): bool
    {
        $cleanName = self::cleanName($cohortName);
        $db = self::getDB();

        $stmt = $db->prepare("SELECT 1 FROM Cohorts WHERE cohortID = ? LIMIT 1");
        $stmt->execute([$cohortID]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        self::stopIfNameTaken($cleanName, $cohortID);

        // all or nothing, so a refused name can't still change who can log in
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE Cohorts SET cohortName = ? WHERE cohortID = ?");
            $stmt->execute([$cleanName, $cohortID]);

            self::applyStatus($cohortID, $isActive);

            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            self::stopIfDuplicateError($e);
            throw $e;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Refuses a name another cohort has, ignoring upper and lower case
     */
    private static function stopIfNameTaken(string $cleanName, ?string $ignoredCohortID = null): void
    {
        $stmt = self::getDB()->prepare(
            "SELECT 1 FROM Cohorts WHERE LOWER(cohortName) = LOWER(?) AND cohortID <> ? LIMIT 1"
        );
        $stmt->execute([$cleanName, $ignoredCohortID ?? '00000000-0000-0000-0000-000000000000']);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException(self::DUPLICATE_NAME);
        }
    }

    /**
     * Two admins saving the same name at once gets past the check above, the unique key stops the second
     */
    private static function stopIfDuplicateError(\PDOException $e): void
    {
        if ((string) $e->getCode() === '23000') {
            throw new \InvalidArgumentException(self::DUPLICATE_NAME);
        }
    }

    /**
     * Deletes a cohort, as long as it has no students or subjects
     * The check is part of the DELETE, so one can't be added between checking and deleting
     *
     * @return bool false if there's no cohort with this ID
     * @throws \InvalidArgumentException if it still has students or subjects
     */
    public static function delete(string $cohortID): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare(
            "DELETE FROM Cohorts WHERE cohortID = ?
                AND NOT EXISTS (SELECT 1 FROM Students WHERE Students.cohortID = ?)
                AND NOT EXISTS (SELECT 1 FROM Subjects WHERE Subjects.cohortID = ?)"
        );
        $stmt->execute([$cohortID, $cohortID, $cohortID]);
        if ($stmt->rowCount() === 1) {
            return true;
        }

        $stmt = $db->prepare("SELECT 1 FROM Cohorts WHERE cohortID = ? LIMIT 1");
        $stmt->execute([$cohortID]);
        if ($stmt->fetchColumn() === false) {
            return false;
        }

        throw new \InvalidArgumentException('This cohort still has students or subjects, so it cannot be deleted');
    }

    /**
     * Students in every other cohort, A to Z by last name, for the add students pop-up
     *
     * @return array<int, array<string, string>>
     */
    public static function getStudentsOutside(string $cohortID): array
    {
        $stmt = self::getDB()->prepare(
            "SELECT Students.firstName, Students.lastName, Users.username, Cohorts.cohortName
                FROM Students
                JOIN Users ON Users.userID = Students.userID
                JOIN Cohorts ON Cohorts.cohortID = Students.cohortID
                WHERE Students.cohortID <> ?
                ORDER BY Students.lastName, Students.firstName"
        );
        $stmt->execute([$cohortID]);

        $students = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $students[] = [
                'studentNumber' => $row['username'],
                'name' => $row['firstName'] . ' ' . $row['lastName'],
                'currentCohort' => $row['cohortName'],
            ];
        }

        return $students;
    }

    /**
     * Moves students into a cohort, all of them or none. A student's in one cohort, so this takes them out of the last
     * Their account follows the new cohort: into a disabled one they're locked out like everyone else in it,
     * out of a disabled one they can log in again, unless they were disabled for another reason
     *
     * @param array<int, string> $studentNumbers
     * @return bool false if there's no cohort with this ID
     * @throws \InvalidArgumentException if a student doesn't exist or is already in this cohort
     */
    public static function moveStudents(string $cohortID, array $studentNumbers): bool
    {
        $db = self::getDB();

        // SQLite does not support FOR UPDATE.
        $lockClause = $db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';

        // always locked in the same order, so two admins moving some of the same students can't deadlock
        sort($studentNumbers);

        $db->beginTransaction();
        try {
            // locked inside the transaction, so the cohort can't be disabled or enabled while students move into it
            // the database's own copy of the ID, MariaDB also matches other spellings of a UUID, like uppercase
            $stmt = $db->prepare("SELECT cohortID, isActive FROM Cohorts WHERE cohortID = ? LIMIT 1" . $lockClause);
            $stmt->execute([$cohortID]);
            $cohort = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($cohort === false) {
                $db->rollBack();
                return false;
            }

            // locks the student and their account, so another admin can't move them at the same time
            $findStudent = $db->prepare(
                "SELECT Students.userID, Students.cohortID FROM Students
                    JOIN Users ON Users.userID = Students.userID
                    WHERE Users.username = ? LIMIT 1" . $lockClause
            );
            $moveStudent = $db->prepare("UPDATE Students SET cohortID = ? WHERE userID = ?");
            $updateAccount = $db->prepare(
                (int) $cohort['isActive'] === 1
                    ? "UPDATE Users SET isEnabled = 1, disabledByCohort = 0 WHERE disabledByCohort = 1 AND userID = ?"
                    : "UPDATE Users SET isEnabled = 0, disabledByCohort = 1 WHERE isEnabled = 1 AND userID = ?"
            );

            foreach ($studentNumbers as $studentNumber) {
                $findStudent->execute([$studentNumber]);
                $student = $findStudent->fetch(\PDO::FETCH_ASSOC);

                // the page can be out of date, someone else might have moved them already
                if ($student === false || $student['cohortID'] === $cohort['cohortID']) {
                    throw new \InvalidArgumentException(
                        'One of these students can no longer be moved here. Reload the page and try again'
                    );
                }

                $moveStudent->execute([$cohort['cohortID'], $student['userID']]);
                $updateAccount->execute([$student['userID']]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Adds subjects to a cohort, all of them or none
     * A subject added to a disabled cohort is archived the same way as if it was there when the cohort was disabled
     *
     * @param array<int, string> $subjectIDs
     * @return bool false if there's no cohort with this ID
     * @throws \InvalidArgumentException if a subject is archived or already in a cohort
     */
    public static function assignSubjects(string $cohortID, array $subjectIDs): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("SELECT isActive FROM Cohorts WHERE cohortID = ? LIMIT 1");
        $stmt->execute([$cohortID]);
        $cohortIsActive = $stmt->fetchColumn();
        if ($cohortIsActive === false) {
            return false;
        }
        $cohortIsActive = (int) $cohortIsActive === 1;

        $stmt = $db->prepare(
            "UPDATE Subjects SET cohortID = ?, isActive = ?, archivedByCohort = ?
                WHERE subjectID = ? AND cohortID IS NULL AND isActive = 1"
        );

        $db->beginTransaction();
        try {
            foreach ($subjectIDs as $subjectID) {
                $stmt->execute([$cohortID, $cohortIsActive ? 1 : 0, $cohortIsActive ? 0 : 1, $subjectID]);

                // nothing changed means someone else got there first, or the ID isn't an unassigned subject
                if ($stmt->rowCount() !== 1) {
                    throw new \InvalidArgumentException(
                        'One of these subjects is no longer available. Reload the page and try again'
                    );
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Takes a subject out of a cohort, its tests and past results stay with the subject
     * If only the cohort being disabled archived it, it becomes active again ready to be assigned elsewhere
     *
     * @return bool false if the subject isn't in this cohort
     */
    public static function removeSubject(string $cohortID, string $subjectID): bool
    {
        $stmt = self::getDB()->prepare(
            "UPDATE Subjects
                SET cohortID = NULL,
                    isActive = CASE WHEN archivedByCohort = 1 THEN 1 ELSE isActive END,
                    archivedByCohort = 0
                WHERE subjectID = ? AND cohortID = ?"
        );
        $stmt->execute([$subjectID, $cohortID]);

        return $stmt->rowCount() === 1;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> cohortID => students, A to Z by last name
     */
    private static function getStudentsByCohort(): array
    {
        $sql = "
            SELECT Students.cohortID, Students.firstName, Students.lastName, Users.username, Users.email,
                Users.lastLogin, Users.isEnabled, Users.approvalStatus
            FROM Students
            JOIN Users ON Users.userID = Students.userID
            ORDER BY Students.lastName, Students.firstName
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        $students = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $status = $row['approvalStatus'] === 'pending' ? 'Pending' : 'Active';
            $students[$row['cohortID']][] = [
                'studentNumber' => $row['username'],
                'name' => $row['firstName'] . ' ' . $row['lastName'],
                'email' => $row['email'],
                'lastLogin' => $row['lastLogin'],
                'status' => (int) $row['isEnabled'] === 1 ? $status : 'Disabled',
            ];
        }

        return $students;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> cohortID => subjects, A to Z
     */
    private static function getSubjectsByCohort(): array
    {
        $sql = "
            SELECT Subjects.subjectID, Subjects.subjectName, Subjects.cohortID, Subjects.isActive,
                (SELECT COUNT(*) FROM Tests WHERE Tests.subjectID = Subjects.subjectID) AS testCount
            FROM Subjects
            ORDER BY Subjects.subjectName
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        $lecturerNames = Subject::getLecturerNames();

        $subjects = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $subjects[$row['cohortID']][] = [
                'subjectID' => $row['subjectID'],
                'subjectName' => $row['subjectName'],
                'testCount' => (int) $row['testCount'],
                'lecturerNames' => $lecturerNames[$row['subjectID']] ?? null,
                'isActive' => (int) $row['isActive'] === 1,
            ];
        }

        return $subjects;
    }
}
