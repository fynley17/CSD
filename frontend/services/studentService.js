import api from './api';

// Fetch all students for the student management page.
export async function getLecturerStudents() {
    const response = await api.get('/students');
    return response.data;
}
