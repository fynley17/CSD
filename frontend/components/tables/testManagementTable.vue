<script setup>
// Import Vue functions for lifecycle management, reactive state and DOM updates.
import { ref, onMounted, nextTick, watch } from 'vue';

// Import DataTables and its Bootstrap 5 integration and styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import the API function used to retrieve lecturer tests.
import { getLecturerTests } from '../../services/testService';
import { tableScrollRegion } from '../../utils/tableScrollRegion';
import { searchTable, toolbarLayout } from '../../utils/tableSearch';

// Register Bootstrap 5 as the DataTables styling integration.
DataTable.use(DataTablesCore);

// Emit selected tests to the parent management page.
const emit = defineEmits(['edit', 'delete']);

// Store tests returned by the backend.
const tests = ref([]);

// Track loading and error states.
const loading = ref(true);
const errorMessage = ref('');

// Store the DataTables component reference.
const dataTable = ref(null);

// the page's search box sits in the toolbar with the filters, the table does the searching
const props = defineProps({
    search: {
        type: String,
        default: '',
    },
});

watch(
    () => props.search,
    (text) => searchTable(dataTable.value, text),
);

// Store unique subjects for the subject filter dropdown.
const subjects = ref([]);

// Configure the test table columns. render.text() shows names as plain text (stops HTML running, XSS).
const columns = [
    { data: 'testName', title: 'Test Name', render: DataTablesCore.render.text() },
    { data: 'subjectName', title: 'Subject', render: DataTablesCore.render.text() },
    { data: 'questionCount', title: 'Questions' },
    {
        data: 'timeLimit',
        title: 'Time Limit',
        render: (value) => (value === null ? 'No time limit' : `${value} minutes`),
    },
    {
        data: 'isActive',
        title: 'Status',
        // the pill comes from the #status slot, sorting and searching use the word
        render: { _: (value) => (value ? 'Active' : 'Archived'), display: '#status' },
    },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
        className: 'text-nowrap',
        render: '#actions',
    },
];

// Configure table searching, sorting, pagination and empty-state messages.
const tableOptions = {
    searching: true,
    // the search box is in the toolbar above the table, see utils/tableSearch.js
    layout: toolbarLayout,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[0, 'asc']],

    initComplete: tableScrollRegion('Tests table'),

    language: {
        emptyTable: 'No tests to display.',
        zeroRecords: 'No tests match your filters.',
    },
};

// Reload tests from the API and rebuild the available subject filter options.
const refreshTests = async () => {
    try {
        errorMessage.value = '';

        const response = await getLecturerTests();

        tests.value = response;

        // Create a sorted list of unique subjects for the subject filter.
        subjects.value = [...new Set(tests.value.map((test) => test.subjectName))]
            .filter(Boolean)
            .sort((a, b) => a.localeCompare(b));

        await nextTick();
    } catch (error) {
        errorMessage.value = 'Unable to load tests. Please try again later.';
        throw error;
    }
};

// Apply the selected subject filter to the Subject column.
const filterBySubject = async (subject) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.column(1).search(subject).draw();
    }
};

// Expose data and functions so the parent management page can control the table.
defineExpose({
    subjects,
    filterBySubject,
    refreshTests,
});

// Load tests when the component mounts.
onMounted(async () => {
    loading.value = true;

    try {
        await refreshTests();
    } catch {
        // The refresh function already stores the error message.
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <!-- same white card as the lecturer and subject tables -->
    <div class="card border-0 rounded-0 h-100">
        <div class="px-3 px-md-4 pb-4">
            <p v-if="loading" class="text-center py-4 mb-0" role="status">Loading tests...</p>

            <div v-else-if="errorMessage" class="alert alert-danger mb-0" role="alert">
                {{ errorMessage }}
            </div>

            <div v-else class="adminTable">
                <DataTable
                    ref="dataTable"
                    :columns="columns"
                    :data="tests"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                >
                    <!-- screen readers read the caption out, the page heading already says it on screen -->
                    <caption class="visually-hidden">
                        Tests
                    </caption>

                    <!-- house style pill, grey for Archived like the subjects table -->
                    <template #status="props">
                        <span class="statusPill" :class="props.rowData.isActive ? 'statusActive' : 'statusArchived'">
                            {{ props.rowData.isActive ? 'Active' : 'Archived' }}
                        </span>
                    </template>

                    <!-- archived tests can't be edited or deleted again -->
                    <template #actions="props">
                        <span v-if="!props.rowData.isActive" class="text-body-secondary">No actions available</span>
                        <template v-else>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary me-1"
                                :aria-label="'Edit ' + props.rowData.testName"
                                @click="emit('edit', props.rowData)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                :aria-label="'Delete ' + props.rowData.testName"
                                @click="emit('delete', props.rowData)"
                            >
                                Delete
                            </button>
                        </template>
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
</template>
