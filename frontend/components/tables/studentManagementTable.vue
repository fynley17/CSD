<script setup>
import { computed, onMounted, ref, nextTick, watch } from 'vue';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import Popup from '../common/popupDialog.vue';
import {
    getLecturerStudents,
    updateStudentStatus,
    approveStudent,
    declineStudent,
    deleteStudent,
} from '../../services/studentService';
import { formatLastLogin } from '../../utils/formatLastLogin';
import { tableScrollRegion } from '../../utils/tableScrollRegion';
import { searchTable, toolbarLayout } from '../../utils/tableSearch';

DataTable.use(DataTablesCore);

const students = ref([]);
const loading = ref(true);
const errorMessage = ref('');

const deletePopup = ref(null);
const declinePopup = ref(null);
const disablePopup = ref(null);

const studentToDelete = ref(null);
const studentToDecline = ref(null);
const studentToDisable = ref(null);

const deletingStudent = ref(false);
const decliningStudent = ref(false);
const disablingStudent = ref(false);

// Count the student applications that are awaiting approval.
const pendingCount = computed(() => students.value.filter((student) => student.status === 'Pending').length);

const emit = defineEmits(['edit', 'success', 'error']);

const dataTable = ref(null);

// the page's search box sits in the toolbar with the filters, the table does the searching
const props = defineProps({
    search: {
        type: String,
        default: '',
    },
    status: {
        type: String,
        default: '',
    },
});

watch(
    () => props.search,
    (text) => searchTable(dataTable.value, text),
);
watch([() => props.status, loading], ([status]) => {
    if (!loading.value) {
        filterByStatus(status);
    }
});
const cohorts = ref([]);

// house style pill for each status, the word is shown too so it isn't colour alone
const statusPillClasses = {
    Active: 'statusActive',
    Pending: 'statusPending',
    Disabled: 'statusDisabled',
};

// render.text() shows names and emails as plain text (stops HTML typed into a name running, XSS)
const columns = [
    { data: 'name', title: 'Name', render: DataTablesCore.render.text() },
    { data: 'studentNumber', title: 'Student Number', render: DataTablesCore.render.text() },
    { data: 'email', title: 'Email', render: DataTablesCore.render.text() },
    {
        data: 'status',
        title: 'Status',
        // the pill comes from the #status slot, sorting and the status filter use the plain word
        render: { _: (value) => value, display: '#status' },
    },
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
    { data: 'cohort', title: 'Cohort', render: DataTablesCore.render.text() },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
        className: 'text-nowrap',
        render: '#actions',
    },
];

const tableOptions = {
    searching: true,
    // the search box is in the toolbar above the table, see utils/tableSearch.js
    layout: toolbarLayout,
    ordering: true,
    paging: true,
    pageLength: 5,
    lengthChange: false,
    order: [[0, 'asc']],
    initComplete: tableScrollRegion('Students table'),
    language: {
        emptyTable: 'No students to display.',
        zeroRecords: 'No students match your filters.',
    },
};

// Refresh the student records and available cohort options.
const refreshStudents = async () => {
    students.value = await getLecturerStudents();

    cohorts.value = [...new Set(students.value.map((student) => student.cohort).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b),
    );
};

// Filter the table by cohort, matching the complete cohort name.
const filterByCohort = async (cohort) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = cohort ? `^${cohort.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$` : '';

        // Cohort is the sixth column (index 5).
        tableApi.column(5).search(searchValue, true, false).draw();
    }
};

const filterByStatus = async (status) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = status ? `^${status}$` : '';

        // Status is the fourth column (index 3).
        tableApi.column(3).search(searchValue, true, false).draw();
    }
};

// Expose the cohort options and filter methods to the parent view.
defineExpose({
    cohorts,
    pendingCount,
    filterByCohort,
    refreshStudents,
});

// Middle button says Enable or Disable depending on the account.
const toggleAction = (student) => (student.status === 'Disabled' ? 'enable' : 'disable');
const toggleText = (student) => (student.status === 'Disabled' ? 'Enable' : 'Disable');

// Handle the action buttons in the #actions slot.
const handleAction = async (action, student, event) => {
    if (action === 'edit') {
        emit('edit', student);
        return;
    }

    if (action === 'disable') {
        studentToDisable.value = student;
        await nextTick();
        disablePopup.value?.open();
        return;
    }

    // Open the decline confirmation popup.
    if (action === 'decline') {
        studentToDecline.value = student;
        await nextTick();
        declinePopup.value?.open();
        return;
    }

    // Open the delete confirmation popup.
    if (action === 'delete') {
        studentToDelete.value = student;
        await nextTick();
        deletePopup.value?.open();
        return;
    }

    // DataTables draws the slot once, so the button is disabled directly rather than through Vue
    const button = event?.currentTarget;
    if (button) {
        button.disabled = true;
    }

    try {
        switch (action) {
            case 'enable':
                await updateStudentStatus(student.userID, true);
                break;

            case 'disable':
                await updateStudentStatus(student.userID, false);
                break;

            case 'approve':
                await approveStudent(student.userID);
                break;

            default:
                return;
        }

        const messages = {
            enable: 'Student enabled successfully.',
            disable: 'Student disabled successfully.',
            approve: 'Student application approved successfully.',
        };
        // send the student with enable/disable so the page can show Undo
        emit('success', messages[action], action === 'approve' ? null : student);
        await refreshStudents();
    } catch (error) {
        emit(
            'error',
            error.response?.data?.error ||
                error.response?.data?.message ||
                'Unable to complete this action. Please try again.',
        );
    } finally {
        if (button) {
            button.disabled = false;
        }
    }
};

const confirmDisableStudent = async () => {
    if (!studentToDisable.value || disablingStudent.value) {
        return;
    }

    disablingStudent.value = true;

    try {
        await updateStudentStatus(studentToDisable.value.userID, false);
        disablePopup.value?.close();
        emit('success', 'Student disabled successfully.', studentToDisable.value);
        await refreshStudents();
    } catch (error) {
        emit(
            'error',
            error.response?.data?.error ||
                error.response?.data?.message ||
                'Unable to disable the student account. Please try again.',
        );
    } finally {
        disablingStudent.value = false;
    }
};

// Permanently decline the selected application after confirmation.
const confirmDeclineStudent = async () => {
    if (!studentToDecline.value || decliningStudent.value) {
        return;
    }

    decliningStudent.value = true;

    try {
        await declineStudent(studentToDecline.value.userID);
        declinePopup.value?.close();
        emit('success', 'Student application declined successfully.');
        await refreshStudents();
    } catch (error) {
        emit(
            'error',
            error.response?.data?.error ||
                error.response?.data?.message ||
                'Unable to decline the student application. Please try again.',
        );
    } finally {
        decliningStudent.value = false;
    }
};

// Permanently delete the selected student after confirmation.
const confirmDeleteStudent = async () => {
    if (!studentToDelete.value || deletingStudent.value) {
        return;
    }

    deletingStudent.value = true;

    try {
        await deleteStudent(studentToDelete.value.userID);
        deletePopup.value?.close();
        emit('success', 'Student deleted successfully.');
        await refreshStudents();
    } catch (error) {
        emit(
            'error',
            error.response?.data?.error ||
                error.response?.data?.message ||
                'Unable to delete the student account. Please try again.',
        );
    } finally {
        deletingStudent.value = false;
    }
};

const closeDeclineModal = () => {
    if (decliningStudent.value) {
        return;
    }

    declinePopup.value?.close();
};

const closeDisableModal = () => {
    if (disablingStudent.value) {
        return;
    }

    disablePopup.value?.close();
};

const closeDeleteModal = () => {
    if (deletingStudent.value) {
        return;
    }

    deletePopup.value?.close();
};

const handleDisablePopupClose = () => {
    if (!disablingStudent.value) {
        studentToDisable.value = null;
    }
};

const handleDeclinePopupClose = () => {
    if (!decliningStudent.value) {
        studentToDecline.value = null;
    }
};

const handleDeletePopupClose = () => {
    if (!deletingStudent.value) {
        studentToDelete.value = null;
    }
};

// Retrieve student records when the table first shows.
onMounted(async () => {
    try {
        await refreshStudents();
    } catch {
        errorMessage.value = 'Unable to load students. Please try again later.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <!-- same white card as the lecturer and subject tables -->
    <div class="card border-0 rounded-0 h-100">
        <div class="px-3 px-md-4 pb-4">
            <!-- Show a loading message while student records are being retrieved. -->
            <p v-if="loading" class="text-center py-4 mb-0" role="status">Loading students...</p>

            <!-- Show an error if student records could not be loaded. -->
            <div v-else-if="errorMessage" class="alert alert-danger mb-0" role="alert">
                {{ errorMessage }}
            </div>

            <div v-else class="adminTable">
                <DataTable
                    ref="dataTable"
                    :columns="columns"
                    :data="students"
                    :options="tableOptions"
                    class="table table-sm table-striped table-hover align-middle mb-0"
                >
                    <!-- screen readers read the caption out, the page heading already says it on screen -->
                    <caption class="visually-hidden">
                        Students
                    </caption>

                    <template #status="props">
                        <span class="statusPill" :class="statusPillClasses[props.rowData.status] || 'statusDisabled'">
                            {{ props.rowData.status }}
                        </span>
                    </template>

                    <!-- name goes in each aria-label so a screen reader knows which row the button is for -->
                    <template #actions="props">
                        <template v-if="props.rowData.status === 'Pending'">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary me-1"
                                :aria-label="'Approve ' + props.rowData.name"
                                @click="handleAction('approve', props.rowData, $event)"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                :aria-label="'Decline ' + props.rowData.name"
                                @click="handleAction('decline', props.rowData, $event)"
                            >
                                Decline
                            </button>
                        </template>

                        <template v-else>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary me-1"
                                :aria-label="'Edit ' + props.rowData.name"
                                @click="handleAction('edit', props.rowData, $event)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm me-1"
                                :class="
                                    toggleText(props.rowData) === 'Disable' ? 'tableDisableButton' : 'tableEnableButton'
                                "
                                :aria-label="toggleText(props.rowData) + ' ' + props.rowData.name"
                                @click="handleAction(toggleAction(props.rowData), props.rowData, $event)"
                            >
                                {{ toggleText(props.rowData) }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                :aria-label="'Delete ' + props.rowData.name"
                                @click="handleAction('delete', props.rowData, $event)"
                            >
                                Delete
                            </button>
                        </template>
                    </template>
                </DataTable>
            </div>

            <!-- Disable confirmation popup. -->
            <Popup ref="disablePopup" title="Confirm Disable" @close="handleDisablePopupClose">
                <h3>Disable this student?</h3>

                <p>They will no longer be able to log in until their account is enabled again.</p>

                <p class="studentDeleteName">{{ studentToDisable?.name }}</p>

                <template #buttons>
                    <div class="studentFormActions">
                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            :disabled="disablingStudent"
                            @click="closeDisableModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            class="studentCreateButton"
                            :disabled="disablingStudent"
                            @click="confirmDisableStudent"
                        >
                            {{ disablingStudent ? 'Disabling...' : 'Confirm Disable' }}
                        </button>
                    </div>
                </template>
            </Popup>

            <!-- Decline confirmation popup. -->
            <Popup ref="declinePopup" title="Confirm Decline" @close="handleDeclinePopupClose">
                <h3>Decline this application?</h3>

                <p>Are you sure you want to decline and permanently remove this application?</p>

                <p class="studentDeleteName">
                    {{ studentToDecline?.name }}
                </p>

                <template #buttons>
                    <button
                        type="button"
                        class="btn btn-outline-primary flex-fill"
                        :disabled="decliningStudent"
                        @click="closeDeclineModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="btn btn-danger flex-fill"
                        :disabled="decliningStudent"
                        @click="confirmDeclineStudent"
                    >
                        {{ decliningStudent ? 'Declining...' : 'Confirm Decline' }}
                    </button>
                </template>
            </Popup>

            <!-- Delete confirmation popup. -->
            <Popup ref="deletePopup" title="Confirm Delete" @close="handleDeletePopupClose">
                <h3>Are you sure?</h3>

                <p>Are you sure you want to delete this student? This action cannot be undone.</p>

                <p class="studentDeleteName">
                    {{ studentToDelete?.name }}
                </p>

                <template #buttons>
                    <button
                        type="button"
                        class="btn btn-outline-primary flex-fill"
                        :disabled="deletingStudent"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="btn btn-danger flex-fill"
                        :disabled="deletingStudent"
                        @click="confirmDeleteStudent"
                    >
                        {{ deletingStudent ? 'Deleting...' : 'Confirm Delete' }}
                    </button>
                </template>
            </Popup>
        </div>
    </div>
</template>

<style>
.studentFormActions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    justify-content: flex-end;
    align-items: center;
    gap: var(--spaceSm);
    width: 100%;
}

.studentFormActions button {
    flex: 1 1 0;
    min-height: 42px;
    padding: var(--spaceSm) var(--spaceLg);
    border: 1px solid transparent;
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.studentCancelButton {
    color: var(--colorText);
    background: var(--colorSurface);
    border-color: var(--colorBorderInput);
}

.studentCancelButton:hover:not(:disabled) {
    color: var(--colorPrimary);
    border-color: var(--colorPrimary);
}

.studentCreateButton {
    color: var(--colorSurface);
    background: var(--colorPrimary);
    border-color: var(--colorPrimary);
}

.studentCreateButton:hover:not(:disabled) {
    background: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

.studentFormActions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Centre the selected student's name in the confirmation popup. */
.studentDeleteName {
    margin: var(--spaceXl) 0;
    font-weight: 600;
    text-align: center;
}
</style>
