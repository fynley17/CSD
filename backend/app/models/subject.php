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
     * lecturerNames is null if nobody teaches it, lecturerIDs is then an empty list
     * The IDs are there for the edit form on the subject management page
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(): array
    {
        // test count per subject
        $sql = "
            SELECT Subjects.subjectID, Subjects.subjectName, Subjects.cohortID, Cohorts.cohortName, Subjects.isActive,
                (SELECT COUNT(*) FROM Tests WHERE Tests.subjectID = Subjects.subjectID) AS testCount
            FROM Subjects
            JOIN Cohorts ON Cohorts.cohortID = Subjects.cohortID
            ORDER BY Subjects.isActive DESC, Subjects.subjectName
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        $lecturers = self::getLecturers();

        $subjects = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $lecturer = $lecturers[$row['subjectID']] ?? ['ids' => [], 'names' => null];

            $subjects[] = [
                'subjectID' => $row['subjectID'],
                'subjectName' => $row['subjectName'],
                'cohortID' => $row['cohortID'],
                'cohortName' => $row['cohortName'],
                'testCount' => (int) $row['testCount'],
                'lecturerIDs' => $lecturer['ids'],
                'lecturerNames' => $lecturer['names'],
                'isActive' => (int) $row['isActive'] === 1,
            ];
        }

        return $subjects;
    }

    /**
     * Lecturers for the pick list on the subject form
     */
    public static function getLecturerOptions(): array
    {
        $stmt = self::getDB()->prepare('SELECT userID, firstName, lastName FROM Lecturer ORDER BY lastName, firstName');
        $stmt->execute();

        $options = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $options[] = [
                'lecturerID' => $row['userID'],
                'name' => $row['firstName'] . ' ' . $row['lastName'],
            ];
        }
        return $options;
    }

    /**
     * Lecturers on each subject joined into one string, i.e. "Alex Adams, Zoe Younger"
     * Separate query so a subject with 2 lecturers still shows as one row in the table
     * The IDs go with the names so the edit form can tick the right lecturers
     *
     * @return array<string, array> keyed by subjectID, each has the lecturer ids and the joined names
     */
    private static function getLecturers(): array
    {
        $sql = "
            SELECT LecturerSubjects.subjectID, LecturerSubjects.lecturerID, Lecturer.firstName, Lecturer.lastName
            FROM LecturerSubjects
            JOIN Lecturer ON Lecturer.userID = LecturerSubjects.lecturerID
            ORDER BY Lecturer.lastName, Lecturer.firstName
        ";

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute();

        $lecturers = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $subjectID = $row['subjectID'];
            $name = $row['firstName'] . ' ' . $row['lastName'];

            if (isset($lecturers[$subjectID])) {
                $lecturers[$subjectID]['ids'][] = $row['lecturerID'];
                $lecturers[$subjectID]['names'] .= ', ' . $name;
            } else {
                $lecturers[$subjectID] = ['ids' => [$row['lecturerID']], 'names' => $name];
            }
        }

        return $lecturers;
    }

    /**
     * Just the lecturer names for each subject as one string, i.e. "Alex Adams, Zoe Younger"
     * The cohort management page reads this one
     *
     * @return array<string, string> subjectID => names
     */
    public static function getLecturerNames(): array
    {
        $names = [];
        foreach (self::getLecturers() as $subjectID => $lecturer) {
            $names[$subjectID] = $lecturer['names'];
        }

        return $names;
    }

    /**
     * Saves the create and edit forms, no $subjectID means a new subject (it starts active)
     * Whoever was picked as lecturer replaces the old ones, nobody picked is fine too
     *
     * @param string[] $lecturerIDs
     * @return bool false if the subject being edited doesn't exist
     * @throws \InvalidArgumentException with the message the form shows
     */
    public static function save(?string $subjectID, string $subjectName, string $cohortID, array $lecturerIDs): bool
    {
        $subjectName = trim($subjectName);
        self::checkName($subjectName, $cohortID, $subjectID);
        self::checkCohortAndLecturers($cohortID, $lecturerIDs);
        self::checkCohortLocked($subjectID, $cohortID);

        if ($subjectID !== null && !self::exists($subjectID)) {
            return false;
        }

        $db = self::getDB();

        // the subject and its lecturers are saved together or not at all
        $db->beginTransaction();

        try {
            if ($subjectID === null) {
                $subjectID = self::generateUuid();
                $stmt = $db->prepare(
                    'INSERT INTO Subjects (subjectName, cohortID, isActive, subjectID) VALUES (?, ?, 1, ?)'
                );
            } else {
                $stmt = $db->prepare('UPDATE Subjects SET subjectName = ?, cohortID = ? WHERE subjectID = ?');
            }
            $stmt->execute([$subjectName, $cohortID, $subjectID]);

            $stmt = $db->prepare('DELETE FROM LecturerSubjects WHERE subjectID = ?');
            $stmt->execute([$subjectID]);

            $stmt = $db->prepare('INSERT INTO LecturerSubjects (lecturerID, subjectID) VALUES (?, ?)');
            // array_unique so ticking the same lecturer twice can't make a duplicate row
            foreach (array_unique($lecturerIDs) as $lecturerID) {
                $stmt->execute([$lecturerID, $subjectID]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Archive (false) or restore (true). There's no delete, so tests and results keep their subject
     *
     * @return bool false if the subject doesn't exist
     */
    public static function setActive(string $subjectID, bool $isActive): bool
    {
        if (!self::exists($subjectID)) {
            return false;
        }

        $stmt = self::getDB()->prepare('UPDATE Subjects SET isActive = ? WHERE subjectID = ?');
        $stmt->execute([$isActive ? 1 : 0, $subjectID]);

        return true;
    }

    /**
    /**
     * Cohort and ticked lecturers have to be real ones, if not the database refuses and it comes back as a 500
     *
     * @throws \InvalidArgumentException with the message the form shows
     */
    private static function checkCohortAndLecturers(string $cohortID, array $lecturerIDs): void
    {
        $stmt = self::getDB()->prepare('SELECT 1 FROM Cohorts WHERE cohortID = ?');
        $stmt->execute([$cohortID]);

        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('That cohort no longer exists');
        }

        // the form's own lecturer list, anyone ticked who isnt in it isnt real
        $realIDs = array_column(self::getLecturerOptions(), 'lecturerID');
        foreach ($lecturerIDs as $lecturerID) {
            if (!in_array($lecturerID, $realIDs, true)) {
                throw new \InvalidArgumentException('One of the lecturers no longer exists');
            }
        }
    }

    /**
     * Checks the name server side, the page checks too but can't be trusted
     *
     * @throws \InvalidArgumentException with the message the form shows
     */
    private static function checkName(string $subjectName, string $cohortID, ?string $subjectID): void
    {
        if ($subjectName === '') {
            throw new \InvalidArgumentException('Enter a subject name');
        }

        // the column is varchar(100)
        if (mb_strlen($subjectName) > 100) {
            throw new \InvalidArgumentException('Subject name must be 100 characters or fewer');
        }

        // same name is fine in a different cohort, e.g. Databases for 2 different years
        $sql = 'SELECT 1 FROM Subjects WHERE LOWER(subjectName) = LOWER(?) AND cohortID = ?';
        $values = [$subjectName, $cohortID];

        // the subject being edited doesn't count as its own duplicate
        // only added on edit, on create mariadb cant compare the uuid column to '' so nothing ever matched
        if ($subjectID !== null) {
            $sql .= ' AND subjectID <> ?';
            $values[] = $subjectID;
        }

        $stmt = self::getDB()->prepare($sql);
        $stmt->execute($values);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('This cohort already has a subject with that name');
        }
    }

    /**
     * A subject that has tests stays in its cohort, moving it would move the tests to the new cohort's students
     *
     * @throws \InvalidArgumentException with the message the form shows
     */
    private static function checkCohortLocked(?string $subjectID, string $cohortID): void
    {
        // a new subject has no tests yet
        if ($subjectID === null) {
            return;
        }

        // finds the subject only if it's in another cohort and has at least one test
        $stmt = self::getDB()->prepare(
            'SELECT 1 FROM Subjects WHERE subjectID = ? AND cohortID <> ? '
            . 'AND EXISTS (SELECT 1 FROM Tests WHERE Tests.subjectID = Subjects.subjectID)'
        );
        $stmt->execute([$subjectID, $cohortID]);

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('A subject with tests cannot move to another cohort');
        }
    }

    /**
     * True if there's a subject with this ID
     */
    private static function exists(string $subjectID): bool
    {
        $stmt = self::getDB()->prepare('SELECT 1 FROM Subjects WHERE subjectID = ?');
        $stmt->execute([$subjectID]);

        return $stmt->fetchColumn() !== false;
    }
}
