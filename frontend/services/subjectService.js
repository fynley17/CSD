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
