// Placeholder cohorts for the cohort management page, copied from the wireframe.
// Swap this for a cohortService call once PHP can send the students and subjects for each cohort.
const SAMPLE_SUBJECTS = [
    { subjectID: 'subject-1', subjectName: 'Web Applications', testCount: 6, lecturerNames: 'Jack Kimmins' },
    { subjectID: 'subject-2', subjectName: 'Databases', testCount: 4, lecturerNames: 'Jason Hill' },
    { subjectID: 'subject-3', subjectName: 'Software Testing', testCount: 3, lecturerNames: 'Jack Kimmins' },
    { subjectID: 'subject-4', subjectName: 'Networking', testCount: 2, lecturerNames: 'Jason Hill' },
    { subjectID: 'subject-5', subjectName: 'Cyber Security', testCount: 5, lecturerNames: 'Jack Kimmins' },
];

const WIREFRAME_STUDENTS = [
    {
        studentNumber: 'WS382544',
        name: 'Joe Bloggs',
        email: 'WS382544@weston.ac.uk',
        lastLogin: '29/09/2026 09:42',
        status: 'Active',
    },
    {
        studentNumber: 'WS382551',
        name: 'Sarah Jones',
        email: 'WS382551@weston.ac.uk',
        lastLogin: '28/09/2026 14:10',
        status: 'Active',
    },
    {
        studentNumber: 'WS382563',
        name: 'Alex Brown',
        email: 'WS382563@weston.ac.uk',
        lastLogin: null,
        status: 'Pending',
    },
];

/**
 * Makes enough pretend students for a cohort's total to match the wireframe
 */
function makeStudents(firstNumber, count) {
    return Array.from({ length: count }, (_, index) => {
        const studentNumber = `WS${firstNumber + index}`;

        return {
            studentNumber,
            name: `Sample Student ${index + 1}`,
            email: `${studentNumber}@weston.ac.uk`,
            lastLogin: null,
            status: 'Active',
        };
    });
}

function makeCohort(cohortID, cohortName, createdDate, isActive, students, subjectCount) {
    return { cohortID, cohortName, createdDate, isActive, students, subjects: SAMPLE_SUBJECTS.slice(0, subjectCount) };
}

export const sampleCohorts = [
    makeCohort('cohort-1', 'DTS 2026', '2026-09-01', true, [...WIREFRAME_STUDENTS, ...makeStudents(382600, 21)], 4),
    makeCohort('cohort-2', 'BSc Computing 2025', '2025-09-02', true, makeStudents(372000, 31), 5),
    makeCohort('cohort-3', 'Data Science 2026', '2026-10-05', true, [], 0),
    makeCohort('cohort-4', 'Software Engineering 2024', '2024-09-04', false, makeStudents(362000, 27), 4),
    makeCohort('cohort-5', 'DTS 2025', '2025-09-03', true, makeStudents(372100, 22), 4),
    makeCohort('cohort-6', 'BSc Computing 2024', '2024-09-02', true, makeStudents(362100, 29), 5),
    makeCohort('cohort-7', 'Data Science 2025', '2025-10-06', true, makeStudents(372200, 18), 3),
    makeCohort('cohort-8', 'DTS 2024', '2024-09-03', false, makeStudents(362200, 20), 4),
];
