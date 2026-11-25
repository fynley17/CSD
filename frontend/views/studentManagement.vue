<script setup>
import { computed, ref, onMounted, onUnmounted, nextTick } from 'vue';
import { getCurrentUser } from '../services/authService';
import { updateStudentStatus } from '../services/studentService';
import StudentManagementTable from '../components/tables/studentManagementTable.vue';
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import { nameOptions, studentStatusOptions } from '../utils/filterOptions';
import StudentForm from '../components/forms/studentForm.vue';

const currentUser = ref(null);

// lecturers and admins can both add students, the backend allows either
const canCreateStudent = computed(() => ['lecturer', 'admin'].includes(currentUser.value?.accountType));

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
const successMessage = ref('');
// student from the last disable/enable, null hides Undo
const undoStudent = ref(null);
const errorMessage = ref('');
const errorToast = ref(null);
let messageTimeout = null;
let notificationTimeout = null;

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
    }, 20000);
}

// Display a success toast after a completed action.
async function showSuccess(message, student = null) {
    errorMessage.value = '';
    undoStudent.value = student;
    if (typeof errorToast.value?.hidePopover === 'function') {
        errorToast.value.hidePopover();
    }
    successMessage.value = message;
    if (notificationTimeout) clearTimeout(notificationTimeout);
    notificationTimeout = setTimeout(() => {
        successMessage.value = '';
        notificationTimeout = null;
    }, 20000);
}

function closeSuccess() {
    successMessage.value = '';
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
        notificationTimeout = null;
    }
}

async function closeError() {
    errorMessage.value = '';
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
        notificationTimeout = null;
    }
    if (typeof errorToast.value?.hidePopover === 'function') {
        errorToast.value.hidePopover();
    }
}

function closeWarning() {
    warningMessage.value = '';
    if (messageTimeout) {
        clearTimeout(messageTimeout);
        messageTimeout = null;
    }
}

// Display errors in the browser top layer so they remain visible above native dialogs.
async function showError(message) {
    errorMessage.value = message;
    if (notificationTimeout) clearTimeout(notificationTimeout);
    await nextTick();
    if (
        errorToast.value &&
        typeof errorToast.value.showPopover === 'function' &&
        !errorToast.value.matches(':popover-open')
    ) {
        errorToast.value.showPopover();
    }
    notificationTimeout = setTimeout(() => {
        if (typeof errorToast.value?.hidePopover === 'function') {
            errorToast.value.hidePopover();
        }
        errorMessage.value = '';
        notificationTimeout = null;
    }, 20000);
}

// the row still has its old status at this point so just set it back to that
async function undo() {
    const student = undoStudent.value;
    const turnOn = student.status !== 'Disabled';
    undoStudent.value = null;

    try {
        await updateStudentStatus(student.userID, turnOn);
        showSuccess(turnOn ? 'Student enabled successfully.' : 'Student disabled successfully.');
        studentManagementTable.value?.refreshStudents();
    } catch (error) {
        showError(error.response?.data?.error || 'Unable to undo that change. Please try again.');
    }
}

// Handle successful student form saves.
const handleStudentSaved = (response) => {
    studentManagementTable.value?.refreshStudents();
    showSuccess(selectedStudent.value ? 'Student updated successfully.' : 'Student created successfully.');

    if (response?.emailSent === false) {
        showWarning(
            'Student created, but the login email failed. The student will need their account access email resent.',
        );
    }
};

// Clear the notification timeout when leaving the page.
onUnmounted(() => {
    if (messageTimeout) clearTimeout(messageTimeout);
    if (notificationTimeout) clearTimeout(notificationTimeout);
});

// Show only students whose applications are awaiting approval.
const showPendingApplications = () => {
    statusFilter.value = statusFilter.value === 'Pending' ? '' : 'Pending';
};

// Pass the selected cohort to the student table filter.
const filterByCohort = () => {
    studentManagementTable.value?.filterByCohort(cohortFilter.value);
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
</script>

<template>
    <!-- laid out like lecturer and subject management so every table page matches -->
    <section class="container py-4 adminPage">
        <div v-if="successMessage" class="toastMessage successMessage" role="status" aria-live="polite">
            <span aria-hidden="true">✓</span>
            <span>{{ successMessage }}</span>
            <button
                v-if="undoStudent"
                type="button"
                class="btn btn-sm btn-outline-primary toastUndo"
                :aria-label="'Undo for ' + undoStudent.name"
                @click="undo"
            >
                Undo
            </button>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeSuccess">×</button>
        </div>
        <div
            v-if="errorMessage"
            ref="errorToast"
            popover="manual"
            class="toastMessage errorMessage"
            role="alert"
            aria-live="assertive"
        >
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ errorMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeError">×</button>
        </div>

        <!-- Warn the lecturer when student login details could not be emailed. -->
        <div v-if="warningMessage" class="toastMessage warningMessage" role="status" aria-live="polite">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ warningMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeWarning">×</button>
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
                />
            </div>

            <div class="tableToolbarActions">
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    :class="{ active: statusFilter === 'Pending' }"
                    :aria-pressed="statusFilter === 'Pending'"
                    @click="showPendingApplications"
                >
                    Student Applications ({{ pendingCount }})
                </button>
                <button v-if="canCreateStudent" type="button" class="btn btn-primary" @click="openStudentForm">
                    <span aria-hidden="true">+</span> Add student
                </button>
            </div>
        </div>

        <StudentManagementTable
            ref="studentManagementTable"
            :search="searchText"
            :status="statusFilter"
            @edit="openEditStudentForm"
            @success="showSuccess"
            @error="showError"
        />

        <!-- Display the student form in Add or Edit mode. -->
        <StudentForm
            v-if="showStudentForm"
            :key="selectedStudent?.userID ?? 'new'"
            :student="selectedStudent"
            @saved="handleStudentSaved"
            @error="showError"
            @close="closeStudentForm"
        />
    </section>
</template>

<style scoped>
/* Keep the email-delivery notice visible below the success notification. */
.warningMessage {
    top: calc(var(--spaceLg) + 64px);
}

@media (max-width: 639px) {
    .warningMessage {
        top: calc(var(--spaceMd) + 64px);
    }
}
</style>
