<?php

namespace App\Models;

class Cohort extends Model
{
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
