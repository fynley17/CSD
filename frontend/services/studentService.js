import api from './api';

export async function getLecturerStudents() {
    const response = await api.get('/students');
    return response.data;
}

export async function createStudent(studentData) {
    const response = await api.post('/students', studentData);
    return response.data;
}

export async function updateStudentStatus(userID, isEnabled) {
    const response = await api.put(`/students/${encodeURIComponent(userID)}/status`, { isEnabled });
    return response.data;
}

export async function approveStudent(userID) {
    const response = await api.put(`/students/${encodeURIComponent(userID)}/approve`);
    return response.data;
}

export async function declineStudent(userID) {
    const response = await api.put(`/students/${encodeURIComponent(userID)}/decline`);
    return response.data;
}

export async function updateStudent(userID, studentData) {
    const response = await api.put(`/students/${encodeURIComponent(userID)}`, studentData);
    return response.data;
}

export async function deleteStudent(userID) {
    const response = await api.delete(`/students/${encodeURIComponent(userID)}`);
    return response.data;
}
