<script setup>
import { onMounted, ref, nextTick } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import { getLecturerStudents } from '../../services/studentService';

// Register the Bootstrap 5 version of DataTables.
DataTable.use(DataTablesCore);

// Store the students returned by the backend, along with loading and error states.
const students = ref([]);
const loading = ref(true);
const errorMessage = ref('');

// References used to access the DataTables instance and available cohort options.
const dataTable = ref(null);
const cohorts = ref([]);

// Define the columns displayed in the student management table.
const columns = [
    { data: 'name', title: 'Name' },
    { data: 'studentNumber', title: 'Student Number' },
    { data: 'email', title: 'Email' },
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

            return `<span class="studentStatusBadge ${statusClass}">${value}</span>`;
        },
    },
    {
        data: 'lastLogin',
        title: 'Last Login',
        // Display 'Never' when the student has not logged in.
        render: (value) => value || 'Never',
    },
    { data: 'cohort', title: 'Cohort' },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
        // Display different action buttons depending on the student's account status.
        // These buttons are display-only; their functionality is handled in a later ticket.
        render: (data, type, row) => {
            if (type !== 'display') {
                return '';
            }

            // Pending students can be approved or declined.
            if (row.status === 'Pending') {
                return `
                    <button
                        type="button"
                        class="btn btn-sm tableApproveButton tableActionButton me-2"
                        data-action="approve"
                        data-user-id="${row.userID}"
                        aria-label="Approve ${row.name}"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableDeleteButton tableActionButton"
                        data-action="decline"
                        data-user-id="${row.userID}"
                        aria-label="Decline ${row.name}"
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
                        data-user-id="${row.userID}"
                        aria-label="Edit ${row.name}"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableEnableButton tableActionButton me-2"
                        data-action="enable"
                        data-user-id="${row.userID}"
                        aria-label="Enable ${row.name}"
                    >
                        Enable
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableDeleteButton tableActionButton"
                        data-action="delete"
                        data-user-id="${row.userID}"
                        aria-label="Delete ${row.name}"
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
                    data-user-id="${row.userID}"
                    aria-label="Edit ${row.name}"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDisableButton tableActionButton me-2"
                    data-action="disable"
                    data-user-id="${row.userID}"
                    aria-label="Disable ${row.name}"
                >
                    Disable
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDeleteButton tableActionButton"
                    data-action="delete"
                    data-user-id="${row.userID}"
                    aria-label="Delete ${row.name}"
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

// Filter the table by cohort, matching the complete cohort name.
const filterByCohort = async (cohort) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = cohort ? `^${cohort.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$` : '';

        // Cohort is the sixth column (index 5); an empty value clears the filter.
        tableApi.column(5).search(searchValue, true, false).draw();
    }
};

// Filter the table by student account status.
const filterByStatus = async (status) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = status ? `^${status}$` : '';

        // Status is the fourth column (index 3); an empty value clears the filter.
        tableApi.column(3).search(searchValue, true, false).draw();
    }
};

// Apply the search term across the table without clearing the other filters.
const searchStudents = async (searchTerm) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.search(searchTerm).draw();
    }
};

// Expose the cohort options and filter methods so the parent view can control the table.
defineExpose({
    cohorts,
    filterByCohort,
    filterByStatus,
    searchStudents,
});

// Retrieve student records when the table component is mounted.
onMounted(async () => {
    try {
        students.value = await getLecturerStudents();

        // Build a sorted list of unique cohort names for the cohort filter.
        cohorts.value = [...new Set(students.value.map((student) => student.cohort).filter(Boolean))].sort((a, b) =>
            a.localeCompare(b),
        );
    } catch {
        // Show an error message if the student records cannot be retrieved.
        errorMessage.value = 'Unable to load students. Please try again later.';
    } finally {
        // Hide the loading message whether the request succeeds or fails.
        loading.value = false;
    }
});
</script>

<template>
    <div class="studentTableWrapper">
        <!-- Show a loading message while student records are being retrieved. -->
        <p v-if="loading" role="status">Loading students...</p>

        <!-- Show an error if the student records could not be loaded. -->
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

.tableApproveButton:hover,
.tableEnableButton:hover {
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
</style>
