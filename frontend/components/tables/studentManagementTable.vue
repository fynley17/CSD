<script setup>
import { onMounted, ref } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import { getLecturerStudents } from '../../services/studentService';

DataTable.use(DataTablesCore);

const students = ref([]);
const loading = ref(true);
const errorMessage = ref('');

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

// Fetch students from the API.
const fetchStudents = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        students.value = await getLecturerStudents();
    } catch {
        errorMessage.value = 'Unable to load students. Please try again.';
    } finally {
        loading.value = false;
    }
};

onMounted(fetchStudents);
</script>

<template>
    <div class="table-responsive">
        <p v-if="loading">Loading students...</p>

        <p v-else-if="errorMessage" class="errorMessage">
            {{ errorMessage }}
        </p>

        <DataTable
            v-else
            :columns="columns"
            :data="students"
            :options="tableOptions"
            class="table table-sm table-striped table-hover align-middle mb-0"
        />
    </div>
</template>
