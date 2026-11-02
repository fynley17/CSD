<?php

namespace App\Models;

/**
 * A subject a cohort studies, e.g. Databases. Tests and questions belong to a subject
 * Subjects only get archived (isActive = 0), never deleted, old results keep their subject
 */
class Subject extends Model
{
    /**
     * Every subject with its cohort, test count and lecturers, for the admin dashboard table
     * Active ones first, then archived, both A to Z
     * lecturerNames is null if nobody teaches it, cohortName is null if it isn't in a cohort
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(): array
    {
        // test count per subject
        $sql = "
            SELECT Subjects.subjectID, Subjects.subjectName, Cohorts.cohortName, Subjects.isActive,
                (SELECT COUNT(*) FROM Tests WHERE Tests.subjectID = Subjects.subjectID) AS testCount
            FROM Subjects
            LEFT JOIN Cohorts ON Cohorts.cohortID = Subjects.cohortID
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
     * Active subjects that aren't in a cohort, A to Z, for the assign subjects pop-up
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getUnassigned(): array
    {
        $sql = "
            SELECT Subjects.subjectID, Subjects.subjectName,
                (SELECT COUNT(*) FROM Tests WHERE Tests.subjectID = Subjects.subjectID) AS testCount
            FROM Subjects
            WHERE Subjects.cohortID IS NULL AND Subjects.isActive = 1
            ORDER BY Subjects.subjectName
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        $subjects = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $row['testCount'] = (int) $row['testCount'];
            $subjects[] = $row;
        }

        return $subjects;
    }

    /**
     * Lecturers on each subject joined into one string, i.e. "Alex Adams, Zoe Younger"
     * Separate query so a subject with 2 lecturers still shows as one row in the table
     *
     * @return array<string, string> subjectID => names
     */
    public static function getLecturerNames(): array
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
