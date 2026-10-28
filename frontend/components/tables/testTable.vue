<script setup>
// Import Vue functions for reactive data and loading the table on mount.
import { ref, onMounted } from 'vue';

// Import the Vue integration for DataTables and its Bootstrap 5 styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import the service that retrieves all tests for the lecturer dashboard.
import { getLecturerTests } from '../../services/lecturerService';

// Register the Bootstrap 5 version of DataTables with the Vue component.
DataTable.use(DataTablesCore);

// Store the tests returned by the API.
const tests = ref([]);

// Track whether the API request is still running.
const loading = ref(true);

// Store an error message if the API request fails.
const errorMessage = ref('');

// Define the columns displayed in the All Tests table.
// These properties must match the fields returned by the PHP API.
const columns = [
    {
        data: 'testName',
        title: 'Test Name',
    },
    {
        data: 'subjectName',
        title: 'Subject',
    },
    {
        data: 'questionCount',
        title: 'Questions',
    },
    {
        data: 'timeLimit',
        title: 'Time Limit',
        // Display untimed tests clearly while retaining numeric sorting.
        render(data, type) {
            if (data === null || data === undefined) {
                return type === 'display' ? 'No time limit' : '';
            }

            if (type !== 'display') {
                return data;
            }

            return `${data} minutes`;
        },
    },
];

// Configure the DataTables behaviour.
const tableOptions = {
    // Allow lecturers to search for tests.
    searching: true,

    // Allow sorting by clicking column headings.
    ordering: true,

    // Enable pagination if more tests are added.
    paging: true,

    // Display five tests per page.
    pageLength: 5,

    // Hide the option to change the number of rows per page.
    lengthChange: false,

    // Sort tests alphabetically by name.
    order: [[0, 'asc']],

    // Provide clear messages for empty results and searches.
    language: {
        emptyTable: 'No tests to display.',
        zeroRecords: 'No tests match your search.',
        search: 'Search tests:',
    },
};

// Retrieve all tests when the component loads.
onMounted(async () => {
    try {
        // Call the API service and store the returned test records.
        tests.value = await getLecturerTests();
    } catch {
        // Show a message if the request fails.
        errorMessage.value = 'Unable to load tests. Please try again later.';
    } finally {
        // Stop displaying the loading message whether the request succeeds
        // or fails.
        loading.value = false;
    }
});
</script>

<template>
    <div class="card border-0 rounded-0 h-100">
        <!-- Table heading and link to test management. -->
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 fw-bold mb-0">All Tests</h2>

            <router-link :to="{ name: 'testManagement' }" class="small text-dark"> View all tests </router-link>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <!-- Show a loading message while retrieving test data. -->
            <div v-if="loading" class="text-center text-secondary py-4" role="status">Loading tests...</div>

            <!-- Show an error if the API request fails. -->
            <div v-else-if="errorMessage" class="text-center text-danger py-4" role="alert">
                {{ errorMessage }}
            </div>

            <!-- Render the searchable, sortable DataTable when loading ends. -->
            <div v-else class="table-responsive">
                <DataTable
                    :columns="columns"
                    :data="tests"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                />
            </div>
        </div>
    </div>
</template>
