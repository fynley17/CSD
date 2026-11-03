<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import Popup from '../common/popupDialog.vue';
import {
    getLecturerStudents,
    updateStudentStatus,
    approveStudent,
    declineStudent,
    deleteStudent,
} from '../../services/studentService';

// Register the Bootstrap 5 version of DataTables.
DataTable.use(DataTablesCore);

// Store the students returned by the backend, along with loading and error states.
const students = ref([]);
const loading = ref(true);
const errorMessage = ref('');
const actionError = ref('');
const deletePopup = ref(null);
const studentToDelete = ref(null);
const deletingStudent = ref(false);

// Count the student applications that are awaiting approval.
const pendingCount = computed(() => students.value.filter((student) => student.status === 'Pending').length);

const emit = defineEmits(['edit']);

// References used to access the DataTables instance and available cohort options.
const dataTable = ref(null);
const tableWrapper = ref(null);
const cohorts = ref([]);

// Escape values before inserting them into DataTables-generated HTML.
const escapeHtml = (value) =>
    String(value ?? '').replace(
        /[&<>"']/g,
        (character) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[character],
    );

// Define the columns displayed in the student management table.
const columns = [
    {
        data: 'name',
        title: 'Name',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: 'studentNumber',
        title: 'Student Number',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: 'email',
        title: 'Email',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: 'status',
        title: 'Status',
        // Display each student status using its corresponding badge styling.
        render: (value, type) => {
            if (type !== 'display') {
                return value;
            }

            const statusClasses = {
                Active: 'studentStatusActive',
                Pending: 'studentStatusPending',
                Disabled: 'studentStatusDisabled',
            };

            const statusClass = statusClasses[value] || '';

            return `<span class="studentStatusBadge ${statusClass}">${escapeHtml(value)}</span>`;
        },
    },
    {
        data: 'lastLogin',
        title: 'Last Login',
        // Display 'Never' when the student has not logged in.
        render: (value, type) => (type === 'display' ? escapeHtml(value || 'Never') : value || 'Never'),
    },
    {
        data: 'cohort',
        title: 'Cohort',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
        // Display different action buttons depending on the student's account status.
        render: (data, type, row) => {
            if (type !== 'display') {
                return '';
            }

            // Escape database values before placing them in HTML attributes.
            const userID = escapeHtml(row.userID);
            const name = escapeHtml(row.name);

            // Pending students can be approved or declined.
            if (row.status === 'Pending') {
                return `
                    <button
                        type="button"
                        class="btn btn-sm tableApproveButton tableActionButton me-2"
                        data-action="approve"
                        data-user-id="${userID}"
                        aria-label="Approve ${name}"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableDeleteButton tableActionButton"
                        data-action="decline"
                        data-user-id="${userID}"
                        aria-label="Decline ${name}"
                    >
                        Decline
                    </button>
                `;
            }

            // Disabled students can be edited, enabled or deleted.
            if (row.status === 'Disabled') {
                return `
                    <button
                        type="button"
                        class="btn btn-sm tableEditButton tableActionButton me-2"
                        data-action="edit"
                        data-user-id="${userID}"
                        aria-label="Edit ${name}"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableEnableButton tableActionButton me-2"
                        data-action="enable"
                        data-user-id="${userID}"
                        aria-label="Enable ${name}"
                    >
                        Enable
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableDeleteButton tableActionButton"
                        data-action="delete"
                        data-user-id="${userID}"
                        aria-label="Delete ${name}"
                    >
                        Delete
                    </button>
                `;
            }

            // Active students can be edited, disabled or deleted.
            return `
                <button
                    type="button"
                    class="btn btn-sm tableEditButton tableActionButton me-2"
                    data-action="edit"
                    data-user-id="${userID}"
                    aria-label="Edit ${name}"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDisableButton tableActionButton me-2"
                    data-action="disable"
                    data-user-id="${userID}"
                    aria-label="Disable ${name}"
                >
                    Disable
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDeleteButton tableActionButton"
                    data-action="delete"
                    data-user-id="${userID}"
                    aria-label="Delete ${name}"
                >
                    Delete
                </button>
            `;
        },
    },
];

// Configure DataTables sorting, pagination, searching and empty-state messages.
const tableOptions = {
    searching: true,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[0, 'asc']],
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: 'info',
        bottomEnd: 'paging',
    },
    language: {
        emptyTable: 'No students to display.',
        zeroRecords: 'No students match your filters.',
    },
};

// Refresh the student records and available cohort options.
const refreshStudents = async () => {
    students.value = await getLecturerStudents();

    cohorts.value = [...new Set(students.value.map((student) => student.cohort).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b),
    );
};

// Filter the table by cohort, matching the complete cohort name.
const filterByCohort = async (cohort) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = cohort ? `^${cohort.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$` : '';

        // Cohort is the sixth column (index 5).
        tableApi.column(5).search(searchValue, true, false).draw();
    }
};

// Filter the table by student account status.
const filterByStatus = async (status) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = status ? `^${status}$` : '';

        // Status is the fourth column (index 3).
        tableApi.column(3).search(searchValue, true, false).draw();
    }
};

// Apply the search term without clearing the other filters.
const searchStudents = async (searchTerm) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.search(searchTerm).draw();
    }
};

// Expose the cohort options and filter methods to the parent view.
defineExpose({
    cohorts,
    pendingCount,
    filterByCohort,
    filterByStatus,
    searchStudents,
});

// Handle actions generated by DataTables using event delegation.
const handleActionClick = async (event) => {
    const button = event.target?.closest?.('button[data-action]');

    if (!button || !tableWrapper.value?.contains(button)) {
        return;
    }

    const action = button.dataset.action;
    const userID = button.dataset.userId;

    // Find the selected student and pass their details to the parent view.
    if (action === 'edit') {
        const selectedStudent = students.value.find((student) => String(student.userID) === String(userID));

        if (selectedStudent) {
            emit('edit', selectedStudent);
        } else {
            actionError.value = 'Unable to find the selected student.';
        }

        return;
    }

    if (!userID) {
        actionError.value = 'Unable to identify the selected student.';
        return;
    }

    // Confirm before permanently removing a pending application.
    if (
        action === 'decline' &&
        !window.confirm('Are you sure you want to decline and permanently remove this application?')
    ) {
        return;
    }

    // Open the custom confirmation modal before deleting a student.
    if (action === 'delete') {
        studentToDelete.value = students.value.find((student) => String(student.userID) === String(userID));

        if (!studentToDelete.value) {
            actionError.value = 'Unable to find the selected student.';
            return;
        }

        await nextTick();
        deletePopup.value?.open();
        return;
    }

    button.disabled = true;
    actionError.value = '';

    try {
        switch (action) {
            case 'enable':
                await updateStudentStatus(userID, true);
                break;

            case 'disable':
                await updateStudentStatus(userID, false);
                break;

            case 'approve':
                await approveStudent(userID);
                break;

            case 'decline':
                await declineStudent(userID);
                break;

            default:
                return;
        }

        // Refresh the table after a successful action.
        await refreshStudents();
    } catch (error) {
        actionError.value = error.response?.data?.error || 'Unable to complete this action. Please try again.';
    } finally {
        button.disabled = false;
    }
};

// Permanently delete the selected student after confirmation.
const confirmDeleteStudent = async () => {
    if (!studentToDelete.value || deletingStudent.value) {
        return;
    }

    deletingStudent.value = true;
    actionError.value = '';

    try {
        await deleteStudent(studentToDelete.value.userID);
        deletePopup.value?.close();
        await refreshStudents();
    } catch (error) {
        actionError.value = error.response?.data?.error || 'Unable to delete the student. Please try again.';
    } finally {
        deletingStudent.value = false;
    }
};

// Close the confirmation popup without deleting the student.
const closeDeleteModal = () => {
    if (deletingStudent.value) {
        return;
    }

    deletePopup.value?.close();
};

// Clear the selected student when the popup has finished closing.
const handleDeletePopupClose = () => {
    if (!deletingStudent.value) {
        studentToDelete.value = null;
    }
};

// Retrieve student records and attach the delegated action listener.
onMounted(async () => {
    tableWrapper.value?.addEventListener('click', handleActionClick);

    try {
        await refreshStudents();
    } catch {
        errorMessage.value = 'Unable to load students. Please try again later.';
    } finally {
        loading.value = false;
    }
});

// Remove the listener when the component is destroyed.
onUnmounted(() => {
    tableWrapper.value?.removeEventListener('click', handleActionClick);
});
</script>

<template>
    <div ref="tableWrapper" class="studentTableWrapper">
        <!-- Show errors returned by student account actions. -->
        <p v-if="actionError" role="alert">
            {{ actionError }}
        </p>

        <!-- Show a loading message while student records are being retrieved. -->
        <p v-if="loading" role="status">Loading students...</p>

        <!-- Show an error if student records could not be loaded. -->
        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

        <!-- Display the student table once the data has loaded successfully. -->
        <div v-else class="tableResponsive">
            <DataTable
                ref="dataTable"
                :columns="columns"
                :data="students"
                :options="tableOptions"
                class="table table-sm table-striped table-hover align-middle mb-0"
            />
        </div>

        <!-- Confirm permanent student deletion using the shared popup component. -->
        <Popup ref="deletePopup" title="Confirm Delete" @close="handleDeletePopupClose">
            <h3>Are you sure?</h3>

            <p>Are you sure you want to delete this student? This action cannot be undone.</p>

            <p class="studentDeleteName">
                {{ studentToDelete?.name }}
            </p>

            <template #buttons>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="deletingStudent"
                    @click="closeDeleteModal"
                >
                    Cancel
                </button>

                <button type="button" class="btn btn-danger" :disabled="deletingStudent" @click="confirmDeleteStudent">
                    {{ deletingStudent ? 'Deleting...' : 'Confirm Delete' }}
                </button>
            </template>
        </Popup>
    </div>
</template>

<style>
/* Keep the table within the available page width. */
.studentTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

/* Allow horizontal scrolling when the table is wider than the screen. */
.studentTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

/* Maintain enough width for the table columns and their content. */
.studentTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 1000px;
    margin-bottom: 0;
}

/* Prevent column content from wrapping onto multiple lines. */
.studentTableWrapper .tableResponsive th,
.studentTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

/* Give the action buttons enough room to display side by side. */
.studentTableWrapper .tableResponsive td:last-child {
    min-width: 330px;
}

/* Shared styling for student account status badges. */
.studentStatusBadge {
    display: inline-block;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    line-height: 1.4;
}

/* Use different colours to distinguish each student status. */
.studentStatusActive {
    color: var(--colorSurface);
    background-color: var(--colorSuccess);
}

.studentStatusPending {
    color: var(--colorSurface);
    background-color: var(--colorWarning);
}

.studentStatusDisabled {
    color: var(--colorSurface);
    background-color: var(--colorTextSecondary);
}

/* Shared styling for the student action buttons. */
.tableActionButton {
    min-height: 36px;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
}

/* Edit button styling. */
.tableEditButton {
    color: var(--colorPrimary);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorPrimary);
}

.tableEditButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

/* Approve and enable button styling. */
.tableApproveButton,
.tableEnableButton {
    color: var(--colorSurface);
    background-color: var(--colorSuccess);
    border: 1px solid var(--colorSuccess);
}

/* Both buttons use the success hover colour. */
.tableApproveButton:hover,
.tableEnableButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorSuccessHover);
    border-color: var(--colorSuccessHover);
}

/* Disable button styling. */
.tableDisableButton {
    color: var(--colorWarning);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorWarning);
}

.tableDisableButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorWarning);
}

/* Delete button styling. */
.tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

.tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

/* Make keyboard focus clearly visible when navigating action buttons. */
.tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

/* Centre the selected student's name in the confirmation popup. */
.studentDeleteName {
    margin: var(--spaceXl) 0;
    font-weight: 600;
    text-align: center;
}
</style>
