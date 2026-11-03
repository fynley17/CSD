// Subject requests to PHP, through api.js like the other services
import api from './api';

/**
 * Gets every subject, active ones first
 * Each has subjectName, cohortName, testCount, lecturerNames and isActive
 */
export async function getSubjects() {
    const response = await api.get('/subjects');
    return response.data;
}

/**
 * Lecturers for the pick list on the subject form, each has lecturerID and name
 */
export async function getLecturerOptions() {
    const response = await api.get('/subjects/lecturers');
    return response.data;
}

/**
 * Cohorts for the drop down on the subject form, the same list the register page uses
 */
export async function getCohorts() {
    const response = await api.get('/cohorts');
    return response.data;
}

/**
 * Adds a subject, subjectData has subjectName, cohortID and lecturerIDs
 */
export async function createSubject(subjectData) {
    const response = await api.post('/subjects', subjectData);
    return response.data;
}

/**
 * Saves the edit form for one subject, same details as createSubject
 */
export async function updateSubject(subjectID, subjectData) {
    const response = await api.put('/subjects/' + subjectID, subjectData);
    return response.data;
}

/**
 * Archive sends false, Restore sends true
 */
export async function setSubjectActive(subjectID, isActive) {
    const response = await api.patch('/subjects/' + subjectID + '/active', { isActive: isActive });
    return response.data;
}
