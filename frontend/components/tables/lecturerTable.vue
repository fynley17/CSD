<!-- Lecturers table, the dashboard shows the 5 latest and the management page shows all of them with buttons -->
<template>
    <div class="card border-0 rounded-0 h-100">
        <div
            v-if="!manage"
            class="card-body flex-grow-0 d-flex flex-wrap justify-content-between align-items-center gap-2"
        >
            <h2 class="h5 fw-bold mb-0">Recent Lecturer Activity</h2>
            <router-link to="/lecturer-management" class="small text-dark">View all lecturers</router-link>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <p v-if="isLoading" class="text-center py-4 mb-0" role="status">Loading lecturers</p>

            <div v-else-if="loadError" class="alert alert-danger mb-0" role="alert">
                <strong>There is a problem:</strong> {{ loadError }}
            </div>

            <div v-else class="adminTable">
                <DataTable
                    :columns="tableColumns"
                    :data="shownLecturers"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                >
                    <!-- screen readers read the caption out, the heading above already says it on screen -->
                    <caption class="visually-hidden">
                        Lecturers, latest login first
                    </caption>

                    <!-- house style pill, the word is there so it's not shown by colour alone -->
                    <template #status="props">
                        <span class="statusPill" :class="props.cellData ? 'statusActive' : 'statusDisabled'">
                            {{ props.cellData ? 'Active' : 'Disabled' }}
                        </span>
                    </template>

                    <!-- name goes in each aria-label so a screen reader knows which row the button is for -->
                    <template #actions="props">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary me-1"
                            :aria-label="'Edit ' + props.rowData.name"
                            @click="$emit('edit', props.rowData)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary me-1"
                            :aria-label="toggleText(props.rowData) + ' for ' + props.rowData.name"
                            @click="$emit('toggle', props.rowData)"
                        >
                            {{ toggleText(props.rowData) }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            :aria-label="'Delete ' + props.rowData.name"
                            @click="$emit('delete', props.rowData)"
                        >
                            Delete
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
import { getLecturers, getRecentLecturers } from '../../services/administratorService';

DataTable.use(DataTablesCore);

/**
 * Turns the database time into UK date and time, i.e. 29/09/2026, 16:17
 * @param lastLogin the time from PHP, saved in UTC, or null if they've never logged in
 */
export function formatLastLogin(lastLogin) {
    if (lastLogin === null) {
        return 'Never';
    }

    // Z on the end = UTC, the options below turn it into UK time
    const loginTime = new Date(lastLogin.replace(' ', 'T') + 'Z');

    return loginTime.toLocaleString('en-GB', {
        timeZone: 'Europe/London',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// render.text() shows names and emails as plain text (stops HTML typed into a name running, XSS)
const columns = [
    { data: 'name', title: 'Name', render: DataTablesCore.render.text() },
    { data: 'staffID', title: 'Staff ID', render: DataTablesCore.render.text() },
    { data: 'email', title: 'Email', render: DataTablesCore.render.text() },
    { data: 'isEnabled', title: 'Status', render: '#status' },
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

// buttons for this one are in the #actions slot, only the management page adds it
const actionColumn = { data: null, title: 'Actions', orderable: false, render: '#actions' };

// matches the lecturer dashboard table: 5 a page, newest login first
const tableOptions = {
    searching: true,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[4, 'desc']],
    // on phones the table scrolls sideways, so keyboard users need to be able to focus it and use the arrow keys
    initComplete() {
        const scroller = this.api().table().container().querySelector('.dt-layout-table');
        scroller.setAttribute('tabindex', '0');
        scroller.setAttribute('role', 'region');
        scroller.setAttribute('aria-label', 'Lecturers table');
    },
    language: {
        emptyTable: 'No lecturers to show yet',
        zeroRecords: 'No lecturers match your search',
        search: 'Search lecturers:',
    },
};

export default {
    components: {
        DataTable,
    },
    props: {
        // management page sets this, it gets every lecturer and the Actions column but no heading
        manage: {
            type: Boolean,
            default: false,
        },
        // all / active / disabled from the dropdown on the page
        statusFilter: {
            type: String,
            default: 'all',
        },
    },
    emits: ['edit', 'toggle', 'delete'],

    data() {
        return {
            lecturers: [],
            isLoading: true,
            loadError: '',
            tableOptions,
        };
    },

    computed: {
        /**
         * Only the management page gets the Actions column
         */
        tableColumns() {
            return this.manage ? columns.concat(actionColumn) : columns;
        },

        /**
         * status dropdown filter, the search box is done by DataTables itself
         */
        shownLecturers() {
            if (this.statusFilter === 'active') {
                return this.lecturers.filter((lecturer) => lecturer.isEnabled);
            }
            if (this.statusFilter === 'disabled') {
                return this.lecturers.filter((lecturer) => !lecturer.isEnabled);
            }
            return this.lecturers;
        },
    },

    /**
     * Loads the lecturers as soon as the table shows up
     */
    mounted() {
        this.loadLecturers();
    },

    methods: {
        /**
         * Asks PHP for the lecturers. The page calls it again after an edit, disable or delete so rows are fresh
         */
        async loadLecturers() {
            try {
                // dashboard only wants the 5 latest, the management page wants all of them
                this.lecturers = this.manage ? await getLecturers() : await getRecentLecturers();
                this.loadError = '';
            } catch (error) {
                // PHP's own message if it sent one, otherwise one that still says what failed
                this.loadError = error.response?.data?.error || 'Unable to load the lecturers';
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * middle button says Disable or Enable depending on the account
         */
        toggleText(lecturer) {
            return lecturer.isEnabled ? 'Disable account' : 'Enable account';
        },
    },
};
</script>
