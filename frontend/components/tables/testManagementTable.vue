<script setup>
// Import Vue functions for running code when the component loads
// and storing reactive data.
import { onMounted, ref } from 'vue';

// Import DataTables and its Bootstrap 5 integration and styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import the service function used to retrieve the lecturer's tests.
import { getLecturerTests } from '../../services/lecturerService';

// Register Bootstrap 5 as the DataTables integration.
DataTable.use(DataTablesCore);

// Store the tests returned by the API.
// ref() makes the data reactive so the table updates when it changes.
const tests = ref([]);

// Track whether the tests are still being loaded.
const loading = ref(true);

// Store an error message if the tests cannot be loaded.
const errorMessage = ref('');

// Define the columns displayed in the table.
// The data property identifies the field used for each column.
const columns = [
    { data: 'testName', title: 'Test Name' },
    { data: 'subjectName', title: 'Subject' },
    { data: 'questionCount', title: 'Questions' },

    {
        data: 'timeLimit',
        title: 'Time Limit',

        // Display the time limit in minutes, or show a message
        // when the test does not have a time limit.
        render: (value) => (value === null ? 'No time limit' : `${value} minutes`),
    },

    {
        data: null,
        title: 'Actions',

        // Prevent sorting and searching on the Actions column,
        // because it contains buttons rather than normal data.
        orderable: false,
        searchable: false,

        // Generate the Edit and Delete buttons for each test.
        render: (data, type, row) => {
            // Only generate the buttons when displaying the table.
            // Return an empty value for other DataTables operations.
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

// Configure how DataTables behaves.
const tableOptions = {
    searching: false, // Hide the search feature.
    ordering: true, // Allow columns to be sorted.
    paging: true, // Enable pagination.
    pageLength: 5, // Display five tests per page.
    lengthChange: false, // Prevent users changing the page size.
    order: [[0, 'asc']], // Sort by Test Name alphabetically.

    // Messages displayed when there are no matching records.
    language: {
        emptyTable: 'No tests to display.',
        zeroRecords: 'No tests to display.',
    },
};

// Run this code when the component is mounted.
// Retrieve the tests and handle loading or errors.
onMounted(async () => {
    try {
        // Request the lecturer's tests from the backend service.
        tests.value = await getLecturerTests();
    } catch {
        // Display a user-friendly message if the request fails.
        errorMessage.value = 'Unable to load tests. Please try again later.';
    } finally {
        // Stop displaying the loading message whether the request
        // succeeds or fails.
        loading.value = false;
    }
});
</script>

<template>
    <div class="table-responsive">
        <!-- Display a message while the tests are being retrieved. -->
        <p v-if="loading" role="status">Loading tests...</p>

        <!-- Display an error if the request fails. -->
        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

        <!-- Display the table once loading has finished successfully. -->
        <DataTable
            v-else
            :columns="columns"
            :data="tests"
            :options="tableOptions"
            class="table table-sm table-striped table-hover align-middle mb-0"
        />
    </div>
</template>

<style>
/* Shared styling for both action buttons. */
.tableActionButton {
    min-height: 36px;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
}

/* Style the Edit button using the primary theme colours. */
.tableEditButton {
    color: var(--colorPrimary);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorPrimary);
}

/* Change the Edit button's appearance when hovered over. */
.tableEditButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

/* Style the Delete button using the error theme colours. */
.tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

/* Change the Delete button's appearance when hovered over. */
.tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

/* Provide a visible outline when an action button receives
   keyboard focus, improving keyboard accessibility. */
.tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}
</style>
