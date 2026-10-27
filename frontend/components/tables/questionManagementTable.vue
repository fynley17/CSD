<script setup>
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

DataTable.use(DataTablesCore);

// Define the columns for the question management table.
const columns = [
    { data: 'questionID', title: 'Question ID' },
    { data: 'question', title: 'Question' },
    { data: 'answers', title: 'Answers' },
    { data: 'correctAnswer', title: 'Correct Answer' },
    { data: 'subjectName', title: 'Subject' },
    {
        data: 'percentageCorrect',
        title: 'Percentage Correct',
        render(data, type) {
            if (data === null || data === undefined) {
                return '';
            }

            return type === 'display' ? `${data}%` : data;
        },
    },
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
        emptyTable: 'No questions to display.',
        zeroRecords: 'No questions match your search.',
        search: 'Search questions:',
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
