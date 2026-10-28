<script setup>
// Import Vue functions for reactive data and loading the table on mount.
import { ref, onMounted } from 'vue';

// Import the Vue integration for DataTables and its Bootstrap 5 styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import the service that retrieves recent student activity from the API.
import { getRecentStudentActivity } from '../../services/lecturerService';

// Register the Bootstrap 5 version of DataTables with the Vue component.
DataTable.use(DataTablesCore);

// Store the students returned by the API.
// Vue will update the table when this data changes.
const students = ref([]);

// Track whether the API request is still running.
const loading = ref(true);

// Store an error message if the API request fails.
const errorMessage = ref('');

/**
 * Escape HTML characters before displaying values inside generated HTML.
 * This prevents data values from being interpreted as HTML markup.
 */
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => {
        const entities = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        };

        return entities[character];
    });
}

// Define the columns displayed in the Recent Student Activity table.
// The data properties must match the fields returned by the PHP API.
const columns = [
    {
        data: 'name',
        title: 'Name',
    },
    {
        data: 'studentNumber',
        title: 'Student No.',
    },
    {
        data: 'email',
        title: 'Email',
    },
    {
        data: 'status',
        title: 'Status',

        // Display the student status as a Bootstrap badge.
        // Keep the underlying text for searching and sorting.
        render(data, type) {
            if (type !== 'display') {
                return data ?? '';
            }

            // Use different badge colours for each account status.
            const statusClasses = {
                Active: 'text-bg-success',
                'Pending approval': 'text-bg-warning',
                Disabled: 'text-bg-secondary',
            };

            const cssClass = statusClasses[data] || 'text-bg-secondary';

            return `<span class="badge ${cssClass}">${escapeHtml(data)}</span>`;
        },
    },
    {
        data: 'lastLogin',
        title: 'Last Login',

        // Format the login timestamp for display.
        // Return the original value for sorting so dates remain sortable.
        render(data, type) {
            if (!data) {
                return '—';
            }

            if (type !== 'display') {
                return data;
            }

            // Convert the SQL datetime into a JavaScript date.
            const date = new Date(data.replace(' ', 'T'));

            // Fall back to the original value if the date is invalid.
            if (Number.isNaN(date.getTime())) {
                return data;
            }

            return date.toLocaleString();
        },
    },
];

// Configure the DataTables behaviour.
const tableOptions = {
    // Allow users to search student details.
    searching: true,

    // Allow users to sort by clicking column headings.
    ordering: true,

    // Enable pagination if more rows are supplied in future.
    paging: true,

    // Display five rows per page.
    pageLength: 5,

    // Hide the option to change the number of rows per page.
    lengthChange: false,

    // Sort by Last Login, with the newest login first.
    order: [[4, 'desc']],

    // Provide clear messages for empty results and searches.
    language: {
        emptyTable: 'No recent student activity to display.',
        zeroRecords: 'No students match your search.',
        search: 'Search students:',
    },
};

// Retrieve recent student activity when the component loads.
onMounted(async () => {
    try {
        // Call the API service and store the returned student records.
        students.value = await getRecentStudentActivity();
    } catch {
        // Show a message if the request fails.
        errorMessage.value = 'Unable to load recent student activity.';
    } finally {
        // Stop displaying the loading message whether the request succeeds
        // or fails.
        loading.value = false;
    }
});
</script>

<template>
    <div class="card border-0 rounded-0 h-100">
        <!-- Table heading -->
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 fw-bold mb-0">Recent Student Activity</h2>
            <router-link :to="{ name: 'studentManagement' }" class="small text-dark"> View all students </router-link>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <!-- Show a loading message while retrieving the student data. -->
            <div v-if="loading" class="text-center text-secondary py-4" role="status">
                Loading recent student activity...
            </div>

            <!-- Show an error if the API request fails. -->
            <div v-else-if="errorMessage" class="text-center text-danger py-4" role="alert">
                {{ errorMessage }}
            </div>

            <!-- Render the searchable, sortable DataTable when loading ends. -->
            <div v-else class="table-responsive">
                <DataTable
                    :columns="columns"
                    :data="students"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                />
            </div>
        </div>
    </div>
</template>
