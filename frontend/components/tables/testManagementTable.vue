<script setup>
import { onMounted, onBeforeUnmount, ref, nextTick } from 'vue';

import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';

import { getLecturerTests } from '../../services/lecturerService';

DataTable.use(DataTablesCore);

const emit = defineEmits(['edit', 'delete']);

const tests = ref([]);
const loading = ref(true);
const errorMessage = ref('');
const dataTable = ref(null);
const subjects = ref([]);
const tableContainer = ref(null);

const escapeHtml = (value) =>
    String(value ?? '').replace(
        /[&<>"']/g,
        (character) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[character],
    );

const updateSubjects = () => {
    subjects.value = [...new Set(tests.value.map((test) => test.subjectName))]
        .filter(Boolean)
        .sort((a, b) => a.localeCompare(b));
};

const refreshTests = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const updatedTests = await getLecturerTests();

        tests.value = updatedTests;
        updateSubjects();

        await nextTick();

        const tableApi = dataTable.value?.dt;

        if (tableApi) {
            tableApi.clear();
            tableApi.rows.add(tests.value);
            tableApi.draw();
        }
    } catch {
        errorMessage.value = 'Unable to load tests. Please try again later.';
    } finally {
        loading.value = false;
    }
};

const columns = [
    {
        data: 'testName',
        title: 'Test Name',
    },
    {
        data: 'subjectName',
        title: 'Subject',
    },
    {
        data: 'cohortName',
        title: 'Cohort',
        defaultContent: '—',
    },
    {
        data: 'questionCount',
        title: 'Questions',
    },
    {
        data: 'timeLimit',
        title: 'Time Limit',
        render: (value) => (value === null ? 'No time limit' : `${value} minutes`),
    },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
        render: (data, type, row) => {
            if (type !== 'display') return '';

            const testID = escapeHtml(row.testID);
            const testName = escapeHtml(row.testName);

            return `
                <button
                    type="button"
                    class="btn btn-sm tableEditButton tableActionButton me-2"
                    data-action="edit"
                    data-test-id="${testID}"
                    aria-label="Edit ${testName}"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDeleteButton tableActionButton"
                    data-action="delete"
                    data-test-id="${testID}"
                    aria-label="Delete ${testName}"
                >
                    Delete
                </button>
            `;
        },
    },
];

const tableOptions = {
    searching: true,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[0, 'asc']],
    layout: {
        topStart: null,
        topEnd: null,
        bottomStart: 'info',
        bottomEnd: 'paging',
    },
    language: {
        emptyTable: 'No tests to display.',
        zeroRecords: 'No tests match your filters.',
    },
};

const filterBySubject = async (subject) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.column(1).search(subject).draw();
    }
};

const searchTests = async (searchTerm) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.search(searchTerm).draw();
    }
};

const handleTableAction = (event) => {
    const button = event.target.closest('button[data-action]');

    if (!button) return;

    const { action, testId } = button.dataset;

    const selectedTest = tests.value.find((test) => String(test.testID) === testId);

    if (!selectedTest) return;

    if (action === 'edit') {
        emit('edit', selectedTest);
    }

    if (action === 'delete') {
        emit('delete', selectedTest);
    }
};

defineExpose({
    subjects,
    filterBySubject,
    searchTests,
    refreshTests,
});

onMounted(() => {
    tableContainer.value?.addEventListener('click', handleTableAction);
    refreshTests();
});

onBeforeUnmount(() => {
    tableContainer.value?.removeEventListener('click', handleTableAction);
});
</script>

<template>
    <div ref="tableContainer" class="testTableWrapper">
        <p v-if="loading" role="status">Loading tests...</p>

        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

        <div v-else class="tableResponsive">
            <DataTable
                ref="dataTable"
                :columns="columns"
                :data="tests"
                :options="tableOptions"
                class="table table-sm table-striped table-hover align-middle mb-0"
            />
        </div>
    </div>
</template>

<style>
.testTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.testTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

.testTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 850px;
    margin-bottom: 0;
}

.testTableWrapper .tableResponsive th,
.testTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

.testTableWrapper .tableResponsive td:last-child {
    min-width: 150px;
}

.tableActionButton {
    min-height: 36px;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
}

.tableEditButton {
    color: var(--colorPrimary);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorPrimary);
}

.tableEditButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

.tableDeleteButton {
    color: var(--colorSurface);
    background-color: var(--colorError);
    border: 1px solid var(--colorError);
}

.tableDeleteButton:hover {
    background-color: var(--colorErrorHover);
    border-color: var(--colorErrorHover);
}

.tableActionButton:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}
</style>
