<!-- Subjects table on the admin dashboard, works the same as the lecturer table -->
<template>
    <div class="card border-0 rounded-0 h-100">
        <div
            v-if="!manage"
            class="card-body flex-grow-0 d-flex flex-wrap justify-content-between align-items-center gap-2"
        >
            <h2 class="h5 fw-bold mb-0">Subjects</h2>
            <router-link to="/subject-management" class="small text-dark">View all subjects</router-link>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <p v-if="isLoading" class="text-center py-4 mb-0" role="status">Loading subjects</p>

            <div v-else-if="loadError" class="alert alert-danger mb-0" role="alert">
                <strong>There is a problem:</strong> {{ loadError }}
            </div>

            <div v-else class="adminTable">
                <DataTable
                    :columns="tableColumns"
                    :data="shownSubjects"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                >
                    <caption class="visually-hidden">
                        Subjects, active ones first
                    </caption>

                    <!-- same status pill as the lecturer table, grey for Archived -->
                    <template #status="props">
                        <span class="statusPill" :class="props.cellData ? 'statusActive' : 'statusArchived'">
                            {{ props.cellData ? 'Active' : 'Archived' }}
                        </span>
                    </template>

                    <!-- aria-label has the subject name so a screen reader knows which row its for -->
                    <template #actions="props">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary me-1"
                            :aria-label="'Edit ' + props.rowData.subjectName"
                            @click="$emit('edit', props.rowData)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            :aria-label="(props.rowData.isActive ? 'Archive ' : 'Restore ') + props.rowData.subjectName"
                            @click="$emit('toggle', props.rowData)"
                        >
                            {{ props.rowData.isActive ? 'Archive' : 'Restore' }}
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
</template>

<script>
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import { getSubjects } from '../../services/subjectService';
import { tableScrollRegion } from '../../utils/tableScrollRegion';

DataTable.use(DataTablesCore);

// render.text() = names show as plain text (stops HTML typed into a name from running)
// defaultContent shows when a subject has no cohort or lecturer
const columns = [
    { data: 'subjectName', title: 'Subject', render: DataTablesCore.render.text() },
    // Cohort and Tests hidden under 576px so it fits on a phone
    { data: 'cohortName', title: 'Cohort', render: DataTablesCore.render.text(), defaultContent: 'Not assigned' },

    { data: 'testCount', title: 'Tests' },
    {
        data: 'lecturerNames',
        title: 'Lecturer',
        render: DataTablesCore.render.text(),
        defaultContent: 'Not assigned',
    },
    { data: 'isActive', title: 'Status', render: '#status' },
];

// buttons for this one are down in the #actions slot
const actionColumn = { data: null, title: 'Actions', orderable: false, className: 'text-nowrap', render: '#actions' };

// 5 a page like the lecturer table. Empty order keeps PHP's order (active first)
const tableOptions = {
    searching: true,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [],
    // on phones the table scrolls sideways, so keyboard users need to be able to focus it
    initComplete: tableScrollRegion('Subjects table'),
    language: {
        emptyTable: 'No subjects to show yet',
        zeroRecords: 'No subjects match your search',
        search: 'Search subjects:',
    },
};

export default {
    components: {
        DataTable,
    },
    props: {
        // manage page turns this on, gets the Actions column and loses the dashboard heading
        manage: {
            type: Boolean,
            default: false,
        },
        // all / active / archived from the dropdown
        statusFilter: {
            type: String,
            default: 'all',
        },
    },
    emits: ['edit', 'toggle'],

    data() {
        return {
            subjects: [],
            isLoading: true,
            loadError: '',
            tableOptions,
        };
    },

    computed: {
        /**
         * Only the manage page gets the Actions column.
         * Each table gets its own copies, DataTable swaps '#actions' for this table's slot on the
         * object itself, so shared ones kept the first table's buttons and stopped working when
         * you came back to the page.
         */
        tableColumns() {
            const shownColumns = this.manage ? columns.concat(actionColumn) : columns;
            return shownColumns.map((column) => ({ ...column }));
        },

        /**
         * Status dropdown filter. Search box is done by the table itself
         */
        shownSubjects() {
            if (this.statusFilter === 'active') {
                return this.subjects.filter((subject) => subject.isActive);
            }
            if (this.statusFilter === 'archived') {
                return this.subjects.filter((subject) => !subject.isActive);
            }
            return this.subjects;
        },
    },

    /**
     * Load them as soon as the table shows up
     */
    mounted() {
        this.loadSubjects();
    },

    methods: {
        /**
         * Asks php for the subjects, page calls it again after a save or archive so the rows are fresh
         */
        async loadSubjects() {
            try {
                this.subjects = await getSubjects();
                this.loadError = '';
            } catch (error) {
                // PHP's message if it sent one, otherwise a plain one
                this.loadError = error.response?.data?.error || 'Unable to load the subjects';
            } finally {
                // hides Loading whether it worked or not
                this.isLoading = false;
            }
        },
    },
};
</script>
