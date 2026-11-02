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
        render: (value) => value || 'Never',
    },
    { data: 'cohort', title: 'Cohort' },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
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

        tableApi.column(5).search(searchValue, true, false).draw();
    }
};

// Filter the table by student account status.
const filterByStatus = async (status) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = status ? `^${status}$` : '';

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

        cohorts.value = [...new Set(students.value.map((student) => student.cohort).filter(Boolean))].sort((a, b) =>
            a.localeCompare(b),
        );
    } catch {
        errorMessage.value = 'Unable to load students. Please try again later.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="studentTableWrapper">
        <p v-if="loading" role="status">Loading students...</p>

        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

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
.studentTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.studentTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

.studentTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 1000px;
    margin-bottom: 0;
}

.studentTableWrapper .tableResponsive th,
.studentTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

.studentTableWrapper .tableResponsive td:last-child {
    min-width: 330px;
}

.studentStatusBadge {
    display: inline-block;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    line-height: 1.4;
}

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

.tableActionButton {
    min-height: 36px;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
}

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

/* Approve and Enable buttons. */
.tableApproveButton,
.tableEnableButton {
    color: var(--colorSurface);
    background-color: var(--colorSuccess);
    border: 1px solid var(--colorSuccess);
}

/* Both buttons turn dark green with dark text on hover. */
.tableApproveButton:hover,
.tableEnableButton:hover {
    color: var(--colorText);
    background-color: var(--colorSuccessHover);
    border-color: var(--colorSuccessHover);
}

.tableDisableButton {
    color: var(--colorWarning);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorWarning);
}

.tableDisableButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorWarning);
}

.tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

.tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

/* Keep keyboard focus clearly visible. */
.tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}
</style>
