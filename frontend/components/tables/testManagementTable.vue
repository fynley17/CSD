<script setup>
// Import Vue functions for lifecycle, reactive state and DOM updates.
import { onMounted, onBeforeUnmount, ref, nextTick } from 'vue';

// Import DataTables and its Bootstrap 5 integration and styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import test management API functions.
import { getLecturerTests } from '../../services/testService';

// Register Bootstrap 5 as the DataTables styling integration.
DataTable.use(DataTablesCore);

// Emit selected tests to the parent management page.
const emit = defineEmits(['edit', 'delete']);

// Store tests returned by the backend.
const tests = ref([]);

// Track loading and error states.
const loading = ref(true);
const errorMessage = ref('');

// Store the DataTables component and wrapper references.
const dataTable = ref(null);
const tableWrapper = ref(null);

// Store unique subjects for the subject dropdown.
const subjects = ref([]);

// Escape values before inserting them into generated HTML.
const escapeHtml = (value) => {
    return String(value ?? '').replace(
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
};

// Define the columns displayed in the table.
const columns = [
    { data: 'testName', title: 'Test Name' },
    { data: 'subjectName', title: 'Subject' },
    { data: 'questionCount', title: 'Questions' },
    {
        data: 'timeLimit',
        title: 'Time Limit',
        render: (value) => (value === null ? 'No time limit' : `${value} minutes`),
    },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,

        // Generate Edit and Delete buttons for each row.
        render: (data, type, row) => {
            if (type !== 'display') {
                return '';
            }

            const testID = escapeHtml(row.testID);
            const testName = escapeHtml(row.testName);

            return `
                <button
                    type="button"
                    class="btn btn-sm tableEditButton tableActionButton me-2"
                    data-action="edit"
                    data-test-id="${testID}"
                    aria-label="Edit ${testName}"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDeleteButton tableActionButton"
                    data-action="delete"
                    data-test-id="${testID}"
                    aria-label="Delete ${testName}"
                >
                    Delete
                </button>
            `;
        },
    },
];

// Configure DataTables sorting, pagination and search.
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
        emptyTable: 'No tests to display.',
        zeroRecords: 'No tests match your filters.',
    },
};

// Retrieve tests from the backend.
const refreshTests = async () => {
    try {
        errorMessage.value = '';

        const response = await getLecturerTests();

        tests.value = response;

        // Update the subject dropdown using unique subject names.
        subjects.value = [...new Set(tests.value.map((test) => test.subjectName))]
            .filter(Boolean)
            .sort((a, b) => a.localeCompare(b));

        await nextTick();
    } catch {
        errorMessage.value = 'Unable to load tests. Please try again later.';
    }
};

// Handle clicks on the native Edit and Delete buttons.
// Event delegation allows this to work with dynamically generated rows.
const handleTableClick = (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const button = target?.closest('button[data-action]');

    if (!button) {
        return;
    }

    const action = button.dataset.action;
    const rowElement = button.closest('tr');

    if (!rowElement) {
        return;
    }

    const tableApi = dataTable.value?.dt;
    const selectedTest = tableApi?.row(rowElement).data();

    if (!selectedTest) {
        return;
    }

    if (action === 'edit') {
        emit('edit', selectedTest);
    } else if (action === 'delete') {
        emit('delete', selectedTest);
    }
};

// Filter the Subject column using DataTables.
const filterBySubject = async (subject) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.column(1).search(subject).draw();
    }
};

// Search the table while preserving the subject filter.
const searchTests = async (searchTerm) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.search(searchTerm).draw();
    }
};

// Expose functions and data to the parent page.
defineExpose({
    subjects,
    filterBySubject,
    searchTests,
    refreshTests,
});

// Load tests and register the event listener when mounted.
onMounted(async () => {
    tableWrapper.value?.addEventListener('click', handleTableClick);

    loading.value = true;

    await refreshTests();

    loading.value = false;
});

// Remove the event listener when the component is destroyed.
onBeforeUnmount(() => {
    tableWrapper.value?.removeEventListener('click', handleTableClick);
});
</script>

<template>
    <div ref="tableWrapper" class="testTableWrapper">
        <p v-if="loading" role="status">Loading tests...</p>

        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

        <div v-else class="tableResponsive">
            <DataTable
                ref="dataTable"
                :columns="columns"
                :data="tests"
                :options="tableOptions"
                class="table table-sm table-striped table-hover align-middle mb-0"
            />
        </div>
    </div>
</template>

<style>
.testTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.testTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

.testTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 760px;
    margin-bottom: 0;
}

.testTableWrapper .tableResponsive th,
.testTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

.testTableWrapper .tableResponsive td:last-child {
    min-width: 150px;
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

.tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

.tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

.tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}
</style>
