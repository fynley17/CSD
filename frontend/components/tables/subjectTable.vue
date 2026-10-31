<!-- Subjects table on the admin dashboard, works the same as the lecturer table -->
<template>
    <div class="card border-0 rounded-0 h-100">
        <div class="card-body flex-grow-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
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
                    :columns="columns"
                    :data="subjects"
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

DataTable.use(DataTablesCore);

// render.text() = names show as plain text (stops HTML typed into a name from running)
// defaultContent shows when a subject has no lecturer
const columns = [
    { data: 'subjectName', title: 'Subject', render: DataTablesCore.render.text() },
    // Cohort and Tests hidden under 576px so it fits on a phone
    { data: 'cohortName', title: 'Cohort', render: DataTablesCore.render.text() },
    { data: 'testCount', title: 'Tests' },
    {
        data: 'lecturerNames',
        title: 'Lecturer',
        render: DataTablesCore.render.text(),
        defaultContent: 'Not assigned',
    },
    { data: 'isActive', title: 'Status', render: '#status' },
];

// 5 a page like the lecturer table. Empty order keeps PHP's order (active first)
const tableOptions = {
    searching: true,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [],
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

    data() {
        return {
            subjects: [],
            isLoading: true,
            loadError: '',
            columns,
            tableOptions,
        };
    },

    /**
     * Loads the subjects when the dashboard opens
     */
    async mounted() {
        try {
            this.subjects = await getSubjects();
        } catch (error) {
            // PHP's message if it sent one, otherwise a plain one
            this.loadError = error.response?.data?.error || 'Unable to load the subjects';
        } finally {
            // hides Loading whether it worked or not
            this.isLoading = false;
        }
    },
};
</script>
