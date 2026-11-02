import api from './api';

// Fetch all students for the student management page.
export async function getLecturerStudents() {
    const response = await api.get('/students');
    return response.data;
}

// Create a student account.
export async function createStudent(studentData) {
    const response = await api.post('/students', studentData);
    return response.data;
}

// Enable or disable a student account.
export async function updateStudentStatus(userID, isEnabled) {
    const response = await api.put('/students/status', {
        userID,
        isEnabled,
    });

    return response.data;
}

// Approve a pending student application.
export async function approveStudent(userID) {
    const response = await api.put('/students/approve', {
        userID,
    });

    return response.data;
}

// Decline and permanently delete a pending application.
export async function declineStudent(userID) {
    const response = await api.put('/students/decline', {
        userID,
    });

    return response.data;
}

// Update an existing student's details.
export async function updateStudent(userID, studentData) {
    const response = await api.put('/students/update', {
        userID,
        ...studentData,
    });

    return response.data;
}

// Permanently delete a student account.
export async function deleteStudent(userID) {
    const response = await api.delete('/students', {
        data: { userID },
    });

    return response.data;
}
