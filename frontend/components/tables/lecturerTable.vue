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

            <template v-else>
                <!-- the management page has its own toolbar above the table -->
                <div v-if="!manage" class="tableToolbar">
                    <div class="tableToolbarControls">
                        <TableSearch
                            id="dashboardLecturerSearch"
                            v-model="dashboardSearch"
                            placeholder="Name, staff ID or email"
                        />
                        <TableFilter
                            id="dashboardLecturerStatusFilter"
                            v-model="dashboardStatusFilter"
                            label="Status"
                            :options="statusOptions"
                        />
                    </div>
                </div>
                <div class="adminTable">
                    <DataTable
                        ref="dataTable"
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
                        <!-- someone added from Add lecturer is waiting until they use the emailed link -->
                        <template #status="props">
                            <span
                                v-if="props.rowData.hasPassword === false"
                                class="statusPill statusPending text-nowrap"
                            >
                                Waiting for password
                            </span>
                            <span v-else class="statusPill" :class="props.cellData ? 'statusActive' : 'statusDisabled'">
                                {{ props.cellData ? 'Active' : 'Disabled' }}
                            </span>
                        </template>

                        <!-- name goes in each aria-label so a screen reader knows which row the button is for -->
                        <!-- admin rows get no Edit, Disable or Delete, PHP refuses those for admins anyway -->
                        <template #actions="props">
                            <template v-if="props.rowData.role !== 'admin'">
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
                                    class="btn btn-sm me-1"
                                    :class="props.rowData.isEnabled ? 'tableDisableButton' : 'tableEnableButton'"
                                    :aria-label="toggleText(props.rowData) + ' ' + props.rowData.name"
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
                            <!-- admin rows have no buttons above so this word is what says they're an administrator -->
                            <span v-if="props.rowData.role === 'admin'" class="small text-dark">Administrator</span>
                            <!-- admins get this one too, or a new admin who lost the email would be stuck -->
                            <button
                                v-if="props.rowData.hasPassword === false"
                                type="button"
                                class="btn btn-sm btn-outline-primary ms-1"
                                :aria-label="'Resend set password link to ' + props.rowData.name"
                                @click="$emit('resend', props.rowData)"
                            >
                                Resend link
                            </button>
                        </template>
                    </DataTable>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import TableFilter from './tableFilter.vue';
import TableSearch from './tableSearch.vue';
import { getLecturers, getRecentLecturers } from '../../services/administratorService';
import { lecturerStatusOptions } from '../../utils/filterOptions';
import { formatLastLogin } from '../../utils/formatLastLogin';
import { tableScrollRegion } from '../../utils/tableScrollRegion';
import { searchTable, toolbarLayout } from '../../utils/tableSearch';

DataTable.use(DataTablesCore);

// render.text() shows names and emails as plain text (stops HTML typed into a name running, XSS)
const columns = [
    { data: 'name', title: 'Name', render: DataTablesCore.render.text() },
    // text-nowrap keeps the Staff ID heading on one line now the management page has more columns
    { data: 'staffID', title: 'Staff ID', className: 'text-nowrap', render: DataTablesCore.render.text() },
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
// ps-4 is a gap so the buttons aren't hard up against the right aligned Last Login dates
const actionColumn = {
    data: null,
    title: 'Actions',
    orderable: false,
    className: 'text-nowrap ps-4',
    render: '#actions',
};

// matches the lecturer dashboard table: 5 a page, newest login first
const tableOptions = {
    searching: true,
    // the search box is in the toolbar above the table, see utils/tableSearch.js
    layout: toolbarLayout,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[4, 'desc']],
    // on phones the table scrolls sideways, so keyboard users need to be able to focus it
    initComplete: tableScrollRegion('Lecturers table'),
    language: {
        emptyTable: 'No lecturers to show yet',
        zeroRecords: 'No lecturers match your search',
    },
};

export default {
    components: {
        DataTable,
        TableFilter,
        TableSearch,
    },
    props: {
        // management page sets this, it gets every lecturer and the Actions column but no heading
        manage: {
            type: Boolean,
            default: false,
        },
        // all / active / disabled from the dropdown on the page
        // what's typed in the management page's search box
        search: {
            type: String,
            default: '',
        },
        statusFilter: {
            type: String,
            default: 'all',
        },
    },
    emits: ['edit', 'toggle', 'delete', 'resend'],

    data() {
        return {
            lecturers: [],
            // the dashboard's own status dropdown, the management page passes statusFilter in instead
            dashboardStatusFilter: 'all',
            dashboardSearch: '',
            statusOptions: lecturerStatusOptions,
            isLoading: true,
            loadError: '',
            tableOptions,
        };
    },

    computed: {
        /**
         * The dashboard has its own search box, the management page passes its one in
         */
        searchText() {
            return this.manage ? this.search : this.dashboardSearch;
        },

        /**
         * Only the management page gets the Actions column
         */
        tableColumns() {
            // fresh copies for each table - DataTables swaps '#status' and '#actions' for this table's own slots,
            // a shared copy would keep pointing at the first table ever shown and its buttons wouldn't go anywhere
            const tableColumns = columns.map((column) => Object.assign({}, column));
            // actions go after Last Login so the newest login sort (column 4) still points at the right one
            if (this.manage) {
                tableColumns.push(Object.assign({}, actionColumn));
            }
            return tableColumns;
        },

        /**
         * status dropdown filter, the search box is done by DataTables itself
         */
        shownLecturers() {
            const statusFilter = this.manage ? this.statusFilter : this.dashboardStatusFilter;
            if (statusFilter === 'active') {
                return this.lecturers.filter((lecturer) => lecturer.isEnabled);
            }
            if (statusFilter === 'disabled') {
                return this.lecturers.filter((lecturer) => !lecturer.isEnabled);
            }
            return this.lecturers;
        },
    },

    watch: {
        searchText(text) {
            searchTable(this.$refs.dataTable, text);
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
            return lecturer.isEnabled ? 'Disable' : 'Enable';
        },
    },
};
</script>
