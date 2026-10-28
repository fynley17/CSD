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

// Register Bootstrap 5 as the DataTables styling integration.
DataTable.use(DataTablesCore);

// Store the questions returned by the backend.
const questions = ref([]);

// Track loading and error states so the user receives feedback.
const loading = ref(true);
const errorMessage = ref('');

// Store the DataTables component reference so its API can be accessed.
const dataTable = ref(null);

// Store the unique subjects used to populate the subject dropdown.
const subjects = ref([]);

// Define the columns displayed in the question management table.
const columns = [
    {
        data: 'question',
        title: 'Question',
    },
    {
        data: 'answers',
        title: 'Answers',
        defaultContent: '',
    },
    {
        data: 'correctAnswer',
        title: 'Correct Answer',
        defaultContent: '',
    },
    {
        data: 'subjectName',
        title: 'Subject',
        defaultContent: '',
    },
    {
        data: 'percentageCorrect',
        title: 'Percentage Correct',

        // Display the percentage with a percent sign while retaining
        // the original numeric value for sorting.
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

        // Exclude the Actions column from sorting and searching.
        orderable: false,
        searchable: false,
        defaultContent: '',

        // Generate Edit and Delete buttons for each question.
        render: (data, type, row) => {
            // Return no button markup for non-display operations.
            if (type !== 'display') {
                return '';
            }

            return `
                <button
                    type="button"
                    class="btn btn-sm tableEditButton tableActionButton me-2"
                    data-action="edit"
                    data-question-id="${row.questionID}"
                    aria-label="Edit question"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDeleteButton tableActionButton"
                    data-action="delete"
                    data-question-id="${row.questionID}"
                    aria-label="Delete question"
                >
                    Delete
                </button>
            `;
        },
    },
];

// Configure DataTables searching, sorting and pagination.
const tableOptions = {
    searching: true,
    ordering: true,

    // Display five questions per page without a page-size selector.
    paging: true,
    pageLength: 5,
    lengthChange: false,

    // Sort questions alphabetically when the table first loads.
    order: [[0, 'asc']],

    // Allow the table to use the column widths defined by its layout.
    autoWidth: false,

    // Hide the default search placement because the parent view
    // supplies a custom search input beside the subject filter.
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: 'info',
        bottomEnd: 'paging',
    },

    // Show helpful messages when no questions match the current filters.
    language: {
        emptyTable: 'No questions to display.',
        zeroRecords: 'No questions match your filters.',
        search: 'Search questions:',
    },
};

// Filter the Subject column using the DataTables column-search API.
const filterBySubject = async (subject) => {
    // Wait for Vue updates before accessing the DataTables instance.
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        // Column index 3 is the Subject column.
        // An empty subject clears the subject filter.
        tableApi.column(3).search(subject).draw();
    }
};

// Search the searchable columns using the custom search input
// provided by the parent question management view.
const searchQuestions = async (searchTerm) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        // Apply the text search while preserving the subject filter.
        tableApi.search(searchTerm).draw();
    }
};

// Expose the subject list and filtering functions to the parent view.
defineExpose({
    subjects,
    filterBySubject,
    searchQuestions,
});

// Retrieve the lecturer's questions when the component is mounted.
onMounted(async () => {
    try {
        // Fetch the questions and their associated data from the backend.
        questions.value = await getLecturerQuestions();

        // Extract unique subject names, remove empty values,
        // and sort alphabetically for the dropdown.
        subjects.value = [...new Set(questions.value.map((question) => question.subjectName))]
            .filter(Boolean)
            .sort((a, b) => a.localeCompare(b));
    } catch {
        errorMessage.value = 'Unable to load questions. Please try again.';
    } finally {
        // Stop showing the loading message whether the request succeeds or fails.
        loading.value = false;
    }
});
</script>

<template>
    <div class="questionTableWrapper">
        <!-- Announce the loading status to users and assistive technologies. -->
        <p v-if="loading" role="status">Loading questions...</p>

        <!-- Display an accessible error message if loading fails. -->
        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

        <!-- Display the table after loading completes successfully. -->
        <div v-else class="tableResponsive">
            <DataTable
                ref="dataTable"
                :columns="columns"
                :data="questions"
                :options="tableOptions"
                class="table table-sm table-striped table-hover align-middle mb-0"
            />
        </div>
    </div>
</template>

<style>
/* Keep the table within its parent and prevent it widening the page. */
.questionTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

/* Allow horizontal scrolling when the table exceeds the available width. */
.questionTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

/* Maintain a readable table width instead of squeezing columns too tightly. */
.questionTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 760px;
    margin-bottom: 0;
}

/* Keep headings and cell values on one line for easier reading. */
.questionTableWrapper .tableResponsive th,
.questionTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

/* Allow longer question text to wrap across multiple lines. */
.questionTableWrapper .tableResponsive th:first-child,
.questionTableWrapper .tableResponsive td:first-child {
    min-width: 220px;
    white-space: normal;
}

/* Provide enough space for both action buttons in the final column. */
.questionTableWrapper .tableResponsive td:last-child {
    min-width: 150px;
}

/* Apply shared sizing and typography to the Edit and Delete buttons. */
.questionTableWrapper .tableActionButton {
    min-height: 36px;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
}

/* Style the Edit button using the primary theme colours. */
.questionTableWrapper .tableEditButton {
    color: var(--colorPrimary);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorPrimary);
}

/* Change the Edit button colours on hover. */
.questionTableWrapper .tableEditButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

/* Style the Delete button using the error theme colours. */
.questionTableWrapper .tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

/* Change the Delete button colours on hover. */
.questionTableWrapper .tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

/* Make keyboard focus clearly visible on action buttons. */
.questionTableWrapper .tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}
</style>
