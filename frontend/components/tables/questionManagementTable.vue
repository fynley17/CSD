<script setup>
// Import Vue functions for component lifecycle, reactive state,
// and waiting for Vue's updates before accessing the table.
import { onMounted, ref, nextTick } from 'vue';

// Import DataTables and its Bootstrap 5 integration and styling.
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

// Import the service that retrieves questions for the lecturer dashboard.
import { getLecturerQuestions } from '../../services/questionService';
import { tableScrollRegion } from '../../utils/tableScrollRegion';

// Register Bootstrap 5 as the DataTables styling integration.
DataTable.use(DataTablesCore);

// Emit Edit and Delete actions to the parent question management page.
const emit = defineEmits(['editQuestion', 'deleteQuestion']);

// Store the questions returned by the backend.
const questions = ref([]);

// Track loading and error states so the user receives feedback.
const loading = ref(true);
const errorMessage = ref('');

// Store the DataTables component reference.
const dataTable = ref(null);

// Store the unique subjects used to populate the subject dropdown.
const subjects = ref([]);

// Extract unique subject names and sort them alphabetically.
const updateSubjects = () => {
    subjects.value = [...new Set(questions.value.map((question) => question.subjectName))]
        .filter(Boolean)
        .sort((a, b) => a.localeCompare(b));
};

// Retrieve the latest questions from the backend.
const refreshQuestions = async () => {
    errorMessage.value = '';

    try {
        questions.value = await getLecturerQuestions();
        updateSubjects();

        // Wait for Vue to update before refreshing the table.
        await nextTick();
        dataTable.value?.dt?.draw(false);
    } catch (error) {
        errorMessage.value = 'Unable to load questions. Please try again.';
        throw error;
    } finally {
        loading.value = false;
    }
};

// Filter the Subject column using the DataTables column-search API.
const filterBySubject = async (subject) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.column(3).search(subject).draw();
    }
};

// Expose the question data and functions to the parent page.
defineExpose({
    questions,
    subjects,
    filterBySubject,
    refreshQuestions,
});

// Retrieve questions when the table first shows.
onMounted(async () => {
    try {
        await refreshQuestions();
    } catch {
        // The refresh method already sets the error message.
    }
});

// Define the columns displayed in the question management table.
// render.text() shows questions and answers as plain text (stops HTML typed into them running, XSS).
const columns = [
    { data: 'question', title: 'Question', className: 'questionTextCell', render: DataTablesCore.render.text() },
    {
        data: 'answers',
        title: 'Answers',
        defaultContent: '',
        // join the answers into one line, then show it as plain text
        render: (answers, type) => {
            const answerText = Array.isArray(answers) ? answers.map((answer) => answer.answerText).join(' | ') : '';

            return type === 'display' ? DataTablesCore.util.escapeHtml(answerText) : answerText;
        },
    },
    { data: 'correctAnswer', title: 'Correct Answer', defaultContent: '', render: DataTablesCore.render.text() },
    { data: 'subjectName', title: 'Subject', defaultContent: '', render: DataTablesCore.render.text() },
    {
        data: 'percentageCorrect',
        title: 'Percentage Correct',
        render: (value, type) => {
            if (value === null || value === undefined) {
                return '';
            }

            return type === 'display' ? `${value}%` : value;
        },
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

// Configure DataTables searching, sorting and pagination.
const tableOptions = {
    searching: true,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[0, 'asc']],
    autoWidth: false,
    initComplete: tableScrollRegion('Questions table'),
    language: {
        emptyTable: 'No questions to display.',
        zeroRecords: 'No questions match your filters.',
        search: 'Search questions:',
    },
};
</script>

<template>
    <!-- same white card as the lecturer and subject tables -->
    <div class="card border-0 rounded-0 h-100">
        <div class="px-3 px-md-4 pb-4">
            <!-- Announce the loading status to users and assistive technologies. -->
            <p v-if="loading" class="text-center py-4 mb-0" role="status">Loading questions...</p>

            <!-- Display an accessible error message if loading fails. -->
            <div v-else-if="errorMessage" class="alert alert-danger mb-0" role="alert">
                {{ errorMessage }}
            </div>

            <!-- Display the table after loading completes successfully. -->
            <div v-else class="adminTable">
                <DataTable
                    ref="dataTable"
                    :columns="columns"
                    :data="questions"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                >
                    <!-- screen readers read the caption out, the page heading already says it on screen -->
                    <caption class="visually-hidden">
                        Questions
                    </caption>

                    <!-- the question goes in each aria-label so a screen reader knows which row the button is for -->
                    <template #actions="props">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary me-1"
                            :aria-label="'Edit question: ' + props.rowData.question"
                            @click="emit('editQuestion', props.rowData)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            :aria-label="'Delete question: ' + props.rowData.question"
                            @click="emit('deleteQuestion', props.rowData)"
                        >
                            Delete
                        </button>
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
</template>

<style>
/* long questions wrap onto more lines instead of making the table very wide */
.questionTextCell {
    min-width: 220px;
}
</style>
