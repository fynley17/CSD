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
