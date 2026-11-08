<script setup>
// Import Vue functions for reactive data and loading the table on mount.
import { ref, onMounted } from 'vue';

// Import the Vue integration for DataTables and its Bootstrap 5 styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import the service that retrieves recent student activity from the API.
import { getRecentStudentActivity } from '../../services/lecturerService';
import { formatLastLogin } from '../../utils/formatLastLogin';
import { tableScrollRegion } from '../../utils/tableScrollRegion';

// Register the Bootstrap 5 version of DataTables with the Vue component.
DataTable.use(DataTablesCore);

// Store the students returned by the API.
// Vue will update the table when this data changes.
const students = ref([]);

// Track whether the API request is still running.
const loading = ref(true);

// Store an error message if the API request fails.
const errorMessage = ref('');

// house style pill for each status, the word is shown too so it isn't colour alone
const statusPillClasses = {
    Active: 'statusActive',
    'Pending approval': 'statusPending',
    Disabled: 'statusDisabled',
};

// Define the columns displayed in the Recent Student Activity table.
// The data properties must match the fields returned by the PHP API.
// render.text() shows names and emails as plain text (stops HTML typed into a name running, XSS).
const columns = [
    { data: 'name', title: 'Name', render: DataTablesCore.render.text() },
    { data: 'studentNumber', title: 'Student No.', render: DataTablesCore.render.text() },
    { data: 'email', title: 'Email', render: DataTablesCore.render.text() },
    {
        data: 'status',
        title: 'Status',
        // the pill comes from the #status slot, sorting and searching use the plain word
        render: { _: (value) => value ?? '', display: '#status' },
    },
    {
        data: 'lastLogin',
        title: 'Last Login',
        // sorting needs the raw time to get the date order right. The page shows the UK version
        render(data, type) {
            if (type !== 'display') {
                return data || '';
            }
            return formatLastLogin(data);
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

    // On phones the table scrolls sideways, so keyboard users need to be able to focus it.
    initComplete: tableScrollRegion('Recent student activity table'),

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
        <div class="card-body flex-grow-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 fw-bold mb-0">Recent Student Activity</h2>
            <router-link :to="{ name: 'studentManagement' }" class="small text-dark">View all students</router-link>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <!-- Show a loading message while retrieving the student data. -->
            <p v-if="loading" class="text-center py-4 mb-0" role="status">Loading recent student activity...</p>

            <!-- Show an error if the API request fails. -->
            <div v-else-if="errorMessage" class="alert alert-danger mb-0" role="alert">
                {{ errorMessage }}
            </div>

            <!-- Render the searchable, sortable DataTable when loading ends. -->
            <div v-else class="adminTable">
                <DataTable
                    :columns="columns"
                    :data="students"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                >
                    <!-- screen readers read the caption out, the heading above already says it on screen -->
                    <caption class="visually-hidden">
                        Recent student activity, latest login first
                    </caption>

                    <template #status="props">
                        <span class="statusPill" :class="statusPillClasses[props.rowData.status] || 'statusDisabled'">
                            {{ props.rowData.status }}
                        </span>
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
</template>
