import api from './api';

// Fetch all students for the student management page.
export async function getLecturerStudents() {
    const response = await api.get('/students');
    return response.data;
}

// Create a student account from the lecturer student management page.
export async function createStudent(studentData) {
    const response = await api.post('/students', studentData);
    return response.data;
}
