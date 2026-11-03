// Cohort requests to PHP, through api.js like the other services
import api from './api';

/**
 * Gets every cohort with its students and subjects, for the cohort management page
 */
export async function getCohorts() {
    const response = await api.get('/admin/cohorts');
    return response.data;
}

/**
 * Saves whether a cohort is active, false disables it and true enables it
 * Gives back the cohort, as PHP changes its students and subjects as well
 */
export async function setCohortActive(cohortID, isActive) {
    const response = await api.put(`/admin/cohorts/${encodeURIComponent(cohortID)}/status`, { isActive });
    return response.data;
}

/**
 * Gets the active subjects that aren't in a cohort, each has subjectID, subjectName and testCount
 */
export async function getUnassignedSubjects() {
    const response = await api.get('/admin/subjects/unassigned');
    return response.data;
}

/**
 * Adds subjects to a cohort and gives back the updated cohort
 */
export async function assignSubjects(cohortID, subjectIDs) {
    const response = await api.post(`/admin/cohorts/${encodeURIComponent(cohortID)}/subjects`, { subjectIDs });
    return response.data;
}

/**
 * Takes a subject out of a cohort and gives back the updated cohort
 */
export async function removeSubject(cohortID, subjectID) {
    const response = await api.delete(
        `/admin/cohorts/${encodeURIComponent(cohortID)}/subjects/${encodeURIComponent(subjectID)}`,
    );
    return response.data;
}

/**
 * Deletes a cohort, PHP refuses if it still has students or subjects
 */
export async function deleteCohort(cohortID) {
    const response = await api.delete(`/admin/cohorts/${encodeURIComponent(cohortID)}`);
    return response.data;
}

/**
 * Creates a cohort and gives it back, PHP refuses an empty or duplicate name
 */
export async function createCohort(cohortName, isActive) {
    const response = await api.post('/admin/cohorts', { cohortName, isActive });
    return response.data;
}

/**
 * Saves a cohort's name and status and gives back the updated cohort, PHP refuses an empty or duplicate name
 */
export async function updateCohort(cohortID, cohortName, isActive) {
    const response = await api.put(`/admin/cohorts/${encodeURIComponent(cohortID)}`, { cohortName, isActive });
    return response.data;
}

/**
 * Gets the students in other cohorts, each has studentNumber, name and currentCohort
 */
export async function getStudentsOutsideCohort(cohortID) {
    const response = await api.get(`/admin/cohorts/${encodeURIComponent(cohortID)}/other-students`);
    return response.data;
}

/**
 * Moves students into a cohort, out of the one they're in now
 * Gives back every cohort, as the cohorts they came from have changed too
 */
export async function moveStudents(cohortID, studentNumbers) {
    const response = await api.post(`/admin/cohorts/${encodeURIComponent(cohortID)}/students`, { studentNumbers });
    return response.data;
}
