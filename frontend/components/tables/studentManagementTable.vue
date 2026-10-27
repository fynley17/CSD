<script setup>
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

DataTable.use(DataTablesCore);

// Define the columns for the student management table.
const columns = [
    { data: 'name', title: 'Name' },
    { data: 'studentNumber', title: 'Student Number' },
    { data: 'email', title: 'Email' },
    { data: 'status', title: 'Status' },
    { data: 'lastLogin', title: 'Last Login' },
    { data: 'cohort', title: 'Cohort' },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
        defaultContent: '',
    },
];

// Configure DataTables searching, sorting and pagination.
const tableOptions = {
    searching: true,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[0, 'asc']],
    language: {
        emptyTable: 'No students to display.',
        zeroRecords: 'No students match your search.',
        search: 'Search students:',
    },
};
</script>

<template>
    <div class="table-responsive">
        <DataTable
            :columns="columns"
            :data="[]"
            :options="tableOptions"
            class="table table-sm table-striped table-hover align-middle mb-0"
        />
    </div>
</template>
