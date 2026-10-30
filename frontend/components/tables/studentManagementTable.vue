<script setup>
import { onMounted, ref } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import { getLecturerStudents } from '../../services/studentService';

DataTable.use(DataTablesCore);

// Store the students returned by the backend.
const students = ref([]);

// Track loading and error states.
const loading = ref(true);
const errorMessage = ref('');

// Define the columns displayed in the student management table.
const columns = [
    { data: 'name', title: 'Name' },
    { data: 'studentNumber', title: 'Student Number' },
    { data: 'email', title: 'Email' },
    { data: 'status', title: 'Status' },
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

// Configure DataTables sorting, pagination and searching.
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

// Fetch students from the API when the component loads.
onMounted(async () => {
    try {
        students.value = await getLecturerStudents();
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
                :columns="columns"
                :data="students"
                :options="tableOptions"
                class="table table-sm table-striped table-hover align-middle mb-0"
            />
        </div>
    </div>
</template>

<style>
/* Keep the table within its parent. */
.studentTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

/* Allow horizontal scrolling on smaller screens. */
.studentTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

/* Maintain a readable table width. */
.studentTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 1000px;
    margin-bottom: 0;
}

/* Keep headings and cell values on one line. */
.studentTableWrapper .tableResponsive th,
.studentTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

/* Provide enough space for the action buttons. */
.studentTableWrapper .tableResponsive td:last-child {
    min-width: 330px;
}

/* Shared styling for action buttons. */
.tableActionButton {
    min-height: 36px;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
}

/* Edit button. */
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

.tableApproveButton:hover,
.tableEnableButton:hover {
    background-color: var(--colorSuccessHover);
    border-color: var(--colorSuccessHover);
}

/* Disable button. */
.tableDisableButton {
    color: var(--colorWarning);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorWarning);
}

.tableDisableButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorWarning);
}

/* Delete and Decline buttons. */
.tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

.tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

/* Accessible keyboard focus. */
.tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}
</style>
