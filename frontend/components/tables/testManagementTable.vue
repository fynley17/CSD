<script setup>
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

DataTable.use(DataTablesCore);

// Define the columns for the test management table.
const columns = [
    { data: 'testName', title: 'Test Name' },
    { data: 'subjectName', title: 'Subject' },
    { data: 'questionCount', title: 'Questions' },
    { data: 'timeLimit', title: 'Time Limit' },
    { data: 'status', title: 'Status' },
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
        emptyTable: 'No tests to display.',
        zeroRecords: 'No tests match your search.',
        search: 'Search tests:',
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
