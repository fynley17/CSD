<script setup>
import { computed, ref, onMounted, onUnmounted, nextTick } from 'vue';
import { getCurrentUser } from '../services/authService';
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
    }, 15000);
}

// Display a success toast after a completed action.
async function showSuccess(message) {
    errorMessage.value = '';
    if (typeof errorToast.value?.hidePopover === 'function') {
        errorToast.value.hidePopover();
    }
    successMessage.value = message;
    if (notificationTimeout) clearTimeout(notificationTimeout);
    notificationTimeout = setTimeout(() => {
        successMessage.value = '';
        notificationTimeout = null;
    }, 15000);
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
    }, 15000);
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
</script>

<template>
    <!-- laid out like lecturer and subject management so every table page matches -->
    <section class="container py-4 adminPage">
        <div v-if="successMessage" class="toastMessage successMessage" role="status" aria-live="polite">
            <span aria-hidden="true">✓</span>
            <span>{{ successMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeSuccess">×</button>
        </div>
        <div ref="errorToast" popover="manual" class="toastMessage errorMessage" role="alert" aria-live="assertive">
            <span aria-hidden="true">!</span>
            <span>{{ errorMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeError">×</button>
        </div>

        <!-- Warn the lecturer when student login details could not be emailed. -->
        <div v-if="warningMessage" class="toastMessage warningMessage" role="status" aria-live="polite">
            <span aria-hidden="true">!</span>
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
                    @change="filterByStatus"
                />
            </div>

            <div class="tableToolbarActions">
                <button type="button" class="btn btn-outline-primary" @click="showPendingApplications">
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
/* Match the notification styling used on the Questions management page. */
.toastMessage {
    position: fixed;
    z-index: 1100;
    top: calc(var(--navHeight) + var(--spaceLg));
    right: var(--spaceLg);
    display: flex;
    align-items: center;
    gap: var(--spaceSm);
    width: max-content;
    max-width: min(450px, calc(100vw - 2 * var(--spaceLg)));
    padding: var(--spaceMd) var(--spaceLg);
    border: 2px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font-weight: 600;
}

.toastClose {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
}

.successMessage {
    border-color: var(--colorPrimary);
}

.errorMessage {
    border-color: var(--colorError);
    color: var(--colorError);
    margin: 0;
}

.errorMessage:popover-open {
    position: fixed;
    inset: var(--spaceLg) var(--spaceLg) auto auto;
}

/* Keep the email-delivery notice visible below the success notification. */
.warningMessage {
    top: calc(var(--spaceLg) + 64px);
}

@media (max-width: 639px) {
    .toastMessage {
        top: calc(var(--navHeight) + var(--spaceMd));
        right: var(--spaceMd);
    }

    .warningMessage {
        top: calc(var(--spaceMd) + 64px);
    }
}
</style>
