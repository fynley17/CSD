import api from './api';

// Leave subjectID out for the overall leaderboard. The backend also sends the cohort's
// subjects with every response, for the subject filter.
export async function getLeaderboard(subjectID = '') {
    const path = subjectID ? `/leaderboard/${encodeURIComponent(subjectID)}` : '/leaderboard';
    const response = await api.get(path);
    return response.data;
}
