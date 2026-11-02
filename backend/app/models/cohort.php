<?php

namespace App\Models;

class Cohort extends Model
{
    public static function getAll()
    {
        // Return cohort identifiers and names for the registration form's selector.
        $stmt = self::getDB()->prepare("SELECT * FROM Cohorts");
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
     * Saves whether a cohort is active
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

        $stmt = $db->prepare("UPDATE Cohorts SET isActive = ? WHERE cohortID = ?");
        $stmt->execute([$isActive ? 1 : 0, $cohortID]);

        return true;
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
            SELECT Subjects.subjectID, Subjects.subjectName, Subjects.cohortID,
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
            ];
        }

        return $subjects;
    }
}
