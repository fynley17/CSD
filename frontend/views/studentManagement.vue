<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { getCurrentUser } from '../services/authService';
import StudentManagementTable from '../components/tables/studentManagementTable.vue';
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import { nameOptions, studentStatusOptions } from '../utils/filterOptions';
import StudentForm from '../components/forms/studentForm.vue';

const currentUser = ref(null);

const canCreateStudent = computed(() => currentUser.value?.accountType === 'lecturer');

onMounted(async () => {
    currentUser.value = await getCurrentUser();
});

// Reference the student table component so the parent view can access its filters.
const studentManagementTable = ref(null);

// Store the selected cohort and status, the search box is the table's own.
const searchText = ref('');
const cohortFilter = ref('');
const statusFilter = ref('');

// Store the warning displayed when student login emails fail.
const warningMessage = ref('');
let messageTimeout = null;

// Retrieve the available cohort options from the student table component.
const cohorts = computed(() => studentManagementTable.value?.cohorts ?? []);
const pendingCount = computed(() => studentManagementTable.value?.pendingCount ?? 0);

// Display a temporary warning notification.
function showWarning(message) {
    warningMessage.value = message;

    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }

    messageTimeout = setTimeout(() => {
        warningMessage.value = '';
        messageTimeout = null;
    }, 10000);
}

// Clear the notification timeout when leaving the page.
onUnmounted(() => {
    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }
});

// Show only students whose applications are awaiting approval.
const showPendingApplications = () => {
    statusFilter.value = 'Pending';
    filterByStatus();
};

// Pass the selected cohort to the student table filter.
const filterByCohort = () => {
    studentManagementTable.value?.filterByCohort(cohortFilter.value);
};

// Pass the selected account status to the student table filter.
const filterByStatus = () => {
    studentManagementTable.value?.filterByStatus(statusFilter.value);
};

// Open the form in Add mode.
const openStudentForm = () => {
    selectedStudent.value = null;
    showStudentForm.value = true;
};

// Open the form in Edit mode with the selected student's details.
const openEditStudentForm = (student) => {
    selectedStudent.value = student;
    showStudentForm.value = true;
};

// Close the student form and clear the selected student.
const closeStudentForm = () => {
    showStudentForm.value = false;
    selectedStudent.value = null;
};

// Control the student form and store the student being edited.
const showStudentForm = ref(false);
const selectedStudent = ref(null);

// Refresh the table and warn the lecturer if the login email failed.
const handleStudentSaved = (response) => {
    studentManagementTable.value?.refreshStudents();

    if (response?.emailSent === false) {
        showWarning(
            'Student created, but the login email failed. The student will need their account access email resent.',
        );
    }
};
</script>

<template>
    <!-- laid out like lecturer and subject management so every table page matches -->
    <section class="container py-4 adminPage">
        <!-- Warn the lecturer when student login details could not be emailed. -->
        <div v-if="warningMessage" class="studentManagementToast" role="alert" aria-live="assertive">
            <span aria-hidden="true">!</span>
            {{ warningMessage }}
        </div>

        <h1>Student Management</h1>
        <p>Manage student accounts, approvals and account status</p>

        <!-- search and filters on the left, page buttons on the right -->
        <div class="tableToolbar">
            <div class="tableToolbarControls">
                <TableSearch id="studentSearch" v-model="searchText" placeholder="Name, student number or email" />
                <TableFilter
                    id="studentCohortFilter"
                    v-model="cohortFilter"
                    label="Cohort"
                    :options="nameOptions(cohorts, 'All cohorts')"
                    @change="filterByCohort"
                />
                <TableFilter
                    id="studentStatusFilter"
                    v-model="statusFilter"
                    label="Status"
                    :options="studentStatusOptions"
                    @change="filterByStatus"
                />
            </div>

            <div class="tableToolbarActions">
                <button type="button" class="btn btn-outline-primary" @click="showPendingApplications">
                    Student Applications ({{ pendingCount }})
                </button>
                <button v-if="canCreateStudent" type="button" class="btn btn-primary" @click="openStudentForm">
                    Add student
                </button>
            </div>
        </div>

        <StudentManagementTable ref="studentManagementTable" :search="searchText" @edit="openEditStudentForm" />

        <!-- Display the student form in Add or Edit mode. -->
        <StudentForm
            v-if="showStudentForm"
            :key="selectedStudent?.userID ?? 'new'"
            :student="selectedStudent"
            @saved="handleStudentSaved"
            @close="closeStudentForm"
        />
    </section>
</template>

<style scoped>
/* Display the temporary email failure warning. */
.studentManagementToast {
    position: fixed;
    z-index: 1100;
    top: var(--spaceLg);
    right: var(--spaceLg);
    display: flex;
    align-items: center;
    gap: var(--spaceSm);
    width: max-content;
    max-width: min(450px, calc(100vw - 2 * var(--spaceLg)));
    padding: var(--spaceMd) var(--spaceLg);
    border: 2px solid var(--colorError);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorError);
    font-weight: 600;
}
</style>
