<script setup>
// Import Vue functions for component lifecycle, reactive state,
// and waiting for Vue's updates before accessing the table.
import { onMounted, ref, nextTick } from 'vue';

// Import DataTables and its Bootstrap 5 integration and styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import the service that retrieves the lecturer's tests.
import { getLecturerTests } from '../../services/lecturerService';

// Register Bootstrap 5 as the DataTables styling integration.
DataTable.use(DataTablesCore);

// Store the tests returned by the backend.
const tests = ref([]);

// Track loading and error states so the user receives feedback.
const loading = ref(true);
const errorMessage = ref('');

// Store the DataTables component reference so its API can be accessed.
const dataTable = ref(null);

// Store the unique subjects used to populate the subject dropdown.
const subjects = ref([]);

// Define the columns displayed in the test management table.
const columns = [
    { data: 'testName', title: 'Test Name' },
    { data: 'subjectName', title: 'Subject' },
    { data: 'questionCount', title: 'Questions' },
    {
        data: 'timeLimit',
        title: 'Time Limit',

        // Display the time limit in minutes, or explain when no limit exists.
        render: (value) => (value === null ? 'No time limit' : `${value} minutes`),
    },
    {
        data: null,
        title: 'Actions',

        // Exclude the Actions column from sorting and searching.
        orderable: false,
        searchable: false,

        // Generate Edit and Delete buttons for each test row.
        render: (data, type, row) => {
            // Return no button markup for non-display operations.
            if (type !== 'display') {
                return '';
            }

            return `
                <button
                    type="button"
                    class="btn btn-sm tableEditButton tableActionButton me-2"
                    data-action="edit"
                    data-test-id="${row.testID}"
                    aria-label="Edit ${row.testName}"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDeleteButton tableActionButton"
                    data-action="delete"
                    data-test-id="${row.testID}"
                    aria-label="Delete ${row.testName}"
                >
                    Delete
                </button>
            `;
        },
    },
];

// Configure DataTables sorting, pagination and search behaviour.
const tableOptions = {
    searching: true,
    ordering: true,

    // Display five tests per page without a page-size selector.
    paging: true,
    pageLength: 5,
    lengthChange: false,

    // Sort tests alphabetically by test name when the table loads.
    order: [[0, 'asc']],

    // Hide DataTables' default search placement because the parent
    // component supplies a custom search input beside the subject filter.
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: 'info',
        bottomEnd: 'paging',
    },

    // Show helpful messages when the table has no matching results.
    language: {
        emptyTable: 'No tests to display.',
        zeroRecords: 'No tests match your filters.',
    },
};

// Filter the Subject column using DataTables' column-search API.
const filterBySubject = async (subject) => {
    // Wait for Vue updates before accessing the DataTables instance.
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        // Column index 1 is the Subject column.
        // An empty subject clears this filter.
        tableApi.column(1).search(subject).draw();
    }
};

// Search the searchable columns using the custom search input
// provided by the parent test management view.
const searchTests = async (searchTerm) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        // Apply the text search while preserving the subject filter.
        tableApi.search(searchTerm).draw();
    }
};

// Expose the subject list and filtering functions to the parent view.
defineExpose({
    subjects,
    filterBySubject,
    searchTests,
});

// Retrieve the lecturer's tests when the component is mounted.
onMounted(async () => {
    try {
        tests.value = await getLecturerTests();

        // Extract unique subject names, remove empty values,
        // and sort alphabetically for the dropdown.
        subjects.value = [...new Set(tests.value.map((test) => test.subjectName))]
            .filter(Boolean)
            .sort((a, b) => a.localeCompare(b));
    } catch {
        // Show a user-friendly message if retrieving tests fails.
        errorMessage.value = 'Unable to load tests. Please try again later.';
    } finally {
        // Stop showing the loading message whether the request succeeds or fails.
        loading.value = false;
    }
});
</script>

<template>
    <!-- Keep the table inside a horizontally scrollable container. -->
    <div class="testTableWrapper">
        <!-- Announce loading status to users and assistive technologies. -->
        <p v-if="loading" role="status">Loading tests...</p>

        <!-- Display an accessible error message if loading fails. -->
        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

        <!-- Display the table after loading completes successfully. -->
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
/* Keep the table within its parent and prevent it widening the page. */
.testTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

/* Allow horizontal scrolling when the table exceeds the available width. */
.testTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

/* Maintain a readable table width instead of squeezing columns on mobile. */
.testTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 760px;
    margin-bottom: 0;
}

/* Keep headings and cell values on one line for easier reading. */
.testTableWrapper .tableResponsive th,
.testTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

/* Provide enough space for both action buttons in the final column. */
.testTableWrapper .tableResponsive td:last-child {
    min-width: 150px;
}

/* Shared styling for the Edit and Delete action buttons. */
.tableActionButton {
    min-height: 36px;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
}

/* Style the Edit button with the primary theme colours. */
.tableEditButton {
    color: var(--colorPrimary);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorPrimary);
}

/* Change the Edit button colours on hover. */
.tableEditButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

/* Style the Delete button with the error theme colours. */
.tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

/* Change the Delete button colours on hover. */
.tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

/* Make keyboard focus clearly visible on action buttons. */
.tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}
</style>
