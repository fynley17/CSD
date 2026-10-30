<?php

namespace App\Models;

/**
 * A subject a cohort studies, for example Databases. Tests and questions belong to a subject.
 * A subject can have multiple lecturers, and each lecturer can be assigned to multiple subjects.
 * Subjects are archived (isActive = 0) instead of deleted, so old results still have a subject.
 */
class Subject extends Model
{
    /**
     * Returns every subject with its cohort, test count and lecturers, for the admin dashboard.
     * Active subjects come first, then archived ones, each sorted by name.
     * lecturerNames is null if no lecturer is assigned to the subject.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(): array
    {
        // Count each subject's tests.
        $sql = "
            SELECT Subjects.subjectID, Subjects.subjectName, Cohorts.cohortName, Subjects.isActive,
                (SELECT COUNT(*) FROM Tests WHERE Tests.subjectID = Subjects.subjectID) AS testCount
            FROM Subjects
            JOIN Cohorts ON Cohorts.cohortID = Subjects.cohortID
            ORDER BY Subjects.isActive DESC, Subjects.subjectName
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        $lecturerNames = self::getLecturerNames();

        $subjects = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $subjects[] = [
                'subjectName' => $row['subjectName'],
                'cohortName' => $row['cohortName'],
                'testCount' => (int) $row['testCount'],
                'lecturerNames' => $lecturerNames[$row['subjectID']] ?? null,
                'isActive' => (int) $row['isActive'] === 1,
            ];
        }

        return $subjects;
    }

    /**
     * Returns the lecturers for each subject as one string, for example "Alex Adams, Zoe Younger".
     * This is a separate query so a subject with two lecturers is still one row in the table.
     *
     * @return array<string, string> subjectID => names
     */
    private static function getLecturerNames(): array
    {
        $sql = "
            SELECT LecturerSubjects.subjectID, Lecturer.firstName, Lecturer.lastName
            FROM LecturerSubjects
            JOIN Lecturer ON Lecturer.userID = LecturerSubjects.lecturerID
            ORDER BY Lecturer.lastName, Lecturer.firstName
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        $names = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $subjectID = $row['subjectID'];
            $name = $row['firstName'] . ' ' . $row['lastName'];
            $names[$subjectID] = isset($names[$subjectID]) ? $names[$subjectID] . ', ' . $name : $name;
        }

        return $names;
    }
}
