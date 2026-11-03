<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
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

DataTable.use(DataTablesCore);

const students = ref([]);
const loading = ref(true);
const errorMessage = ref('');
const actionError = ref('');

const deletePopup = ref(null);
const declinePopup = ref(null);

const studentToDelete = ref(null);
const studentToDecline = ref(null);

const deletingStudent = ref(false);
const decliningStudent = ref(false);

const pendingCount = computed(() => students.value.filter((student) => student.status === 'Pending').length);
const emit = defineEmits(['edit']);

const dataTable = ref(null);
const tableWrapper = ref(null);
const cohorts = ref([]);

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

const columns = [
    {
        data: 'name',
        title: 'Name',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: 'studentNumber',
        title: 'Student Number',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: 'email',
        title: 'Email',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: 'status',
        title: 'Status',
        render: (value, type) => {
            if (type !== 'display') {
                return value;
            }

            const statusClasses = {
                Active: 'studentStatusActive',
                Pending: 'studentStatusPending',
                Disabled: 'studentStatusDisabled',
            };

            const statusClass = statusClasses[value] || '';

            return `<span class="studentStatusBadge ${statusClass}">${escapeHtml(value)}</span>`;
        },
    },
    {
        data: 'lastLogin',
        title: 'Last Login',
        render: (value, type) => (type === 'display' ? escapeHtml(value || 'Never') : value || 'Never'),
    },
    {
        data: 'cohort',
        title: 'Cohort',
        render: (value, type) => (type === 'display' ? escapeHtml(value) : value),
    },
    {
        data: null,
        title: 'Actions',
        orderable: false,
        searchable: false,
        render: (data, type, row) => {
            if (type !== 'display') {
                return '';
            }

            const userID = escapeHtml(row.userID);
            const name = escapeHtml(row.name);

            if (row.status === 'Pending') {
                return `
                    <button
                        type="button"
                        class="btn btn-sm tableApproveButton tableActionButton me-2"
                        data-action="approve"
                        data-user-id="${userID}"
                        aria-label="Approve ${name}"
                    >
                        Approve
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableDeleteButton tableActionButton"
                        data-action="decline"
                        data-user-id="${userID}"
                        aria-label="Decline ${name}"
                    >
                        Decline
                    </button>
                `;
            }

            if (row.status === 'Disabled') {
                return `
                    <button
                        type="button"
                        class="btn btn-sm tableEditButton tableActionButton me-2"
                        data-action="edit"
                        data-user-id="${userID}"
                        aria-label="Edit ${name}"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableEnableButton tableActionButton me-2"
                        data-action="enable"
                        data-user-id="${userID}"
                        aria-label="Enable ${name}"
                    >
                        Enable
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm tableDeleteButton tableActionButton"
                        data-action="delete"
                        data-user-id="${userID}"
                        aria-label="Delete ${name}"
                    >
                        Delete
                    </button>
                `;
            }

            return `
                <button
                    type="button"
                    class="btn btn-sm tableEditButton tableActionButton me-2"
                    data-action="edit"
                    data-user-id="${userID}"
                    aria-label="Edit ${name}"
                >
                    Edit
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDisableButton tableActionButton me-2"
                    data-action="disable"
                    data-user-id="${userID}"
                    aria-label="Disable ${name}"
                >
                    Disable
                </button>
                <button
                    type="button"
                    class="btn btn-sm tableDeleteButton tableActionButton"
                    data-action="delete"
                    data-user-id="${userID}"
                    aria-label="Delete ${name}"
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
        emptyTable: 'No students to display.',
        zeroRecords: 'No students match your filters.',
    },
};

const refreshStudents = async () => {
    students.value = await getLecturerStudents();
    cohorts.value = [...new Set(students.value.map((student) => student.cohort).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b),
    );
};

const filterByCohort = async (cohort) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = cohort ? `^${cohort.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$` : '';
        tableApi.column(5).search(searchValue, true, false).draw();
    }
};

const filterByStatus = async (status) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        const searchValue = status ? `^${status}$` : '';
        tableApi.column(3).search(searchValue, true, false).draw();
    }
};

const searchStudents = async (searchTerm) => {
    await nextTick();

    const tableApi = dataTable.value?.dt;

    if (tableApi) {
        tableApi.search(searchTerm).draw();
    }
};

defineExpose({
    cohorts,
    pendingCount,
    filterByCohort,
    filterByStatus,
    searchStudents,
    refreshStudents,
});

const handleActionClick = async (event) => {
    const button = event.target?.closest?.('button[data-action]');

    if (!button || !tableWrapper.value?.contains(button)) {
        return;
    }

    const action = button.dataset.action;
    const userID = button.dataset.userId;

    if (action === 'edit') {
        const selectedStudent = students.value.find((student) => String(student.userID) === String(userID));

        if (selectedStudent) {
            emit('edit', selectedStudent);
        } else {
            actionError.value = 'Unable to find the selected student.';
        }

        return;
    }

    if (!userID) {
        actionError.value = 'Unable to identify the selected student.';
        return;
    }

    if (action === 'decline') {
        studentToDecline.value = students.value.find((student) => String(student.userID) === String(userID));

        if (!studentToDecline.value) {
            actionError.value = 'Unable to find the selected student.';
            return;
        }

        await nextTick();
        declinePopup.value?.open();
        return;
    }

    if (action === 'delete') {
        studentToDelete.value = students.value.find((student) => String(student.userID) === String(userID));

        if (!studentToDelete.value) {
            actionError.value = 'Unable to find the selected student.';
            return;
        }

        await nextTick();
        deletePopup.value?.open();
        return;
    }

    button.disabled = true;
    actionError.value = '';

    try {
        switch (action) {
            case 'enable':
                await updateStudentStatus(userID, true);
                break;

            case 'disable':
                await updateStudentStatus(userID, false);
                break;

            case 'approve':
                await approveStudent(userID);
                break;

            default:
                return;
        }

        await refreshStudents();
    } catch (error) {
        actionError.value = error.response?.data?.error || 'Unable to complete this action. Please try again.';
    } finally {
        button.disabled = false;
    }
};

const confirmDeclineStudent = async () => {
    if (!studentToDecline.value || decliningStudent.value) {
        return;
    }

    decliningStudent.value = true;
    actionError.value = '';

    try {
        await declineStudent(studentToDecline.value.userID);

        declinePopup.value?.close();
        await refreshStudents();
    } catch (error) {
        actionError.value =
            error.response?.data?.error || 'Unable to decline the student application. Please try again.';
    } finally {
        decliningStudent.value = false;
    }
};

const confirmDeleteStudent = async () => {
    if (!studentToDelete.value || deletingStudent.value) {
        return;
    }

    deletingStudent.value = true;
    actionError.value = '';

    try {
        await deleteStudent(studentToDelete.value.userID);

        deletePopup.value?.close();
        await refreshStudents();
    } catch (error) {
        actionError.value = error.response?.data?.error || 'Unable to delete the student account. Please try again.';
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

const closeDeleteModal = () => {
    if (deletingStudent.value) {
        return;
    }

    deletePopup.value?.close();
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

onMounted(async () => {
    tableWrapper.value?.addEventListener('click', handleActionClick);

    try {
        await refreshStudents();
    } catch {
        errorMessage.value = 'Unable to load students. Please try again later.';
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => {
    tableWrapper.value?.removeEventListener('click', handleActionClick);
});
</script>

<template>
    <div ref="tableWrapper" class="studentTableWrapper">
        <p v-if="actionError" role="alert">
            {{ actionError }}
        </p>

        <p v-if="loading" role="status">Loading students...</p>

        <p v-else-if="errorMessage" role="alert">
            {{ errorMessage }}
        </p>

        <div v-else class="tableResponsive">
            <DataTable
                ref="dataTable"
                :columns="columns"
                :data="students"
                :options="tableOptions"
                class="table table-sm table-striped table-hover align-middle mb-0"
            />
        </div>

        <!-- Decline confirmation popup -->
        <Popup ref="declinePopup" title="Confirm Decline" @close="handleDeclinePopupClose">
            <h3>Decline this application?</h3>

            <p>Are you sure you want to decline and permanently remove this application?</p>

            <p class="studentDeleteName">
                {{ studentToDecline?.name }}
            </p>

            <template #buttons>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="decliningStudent"
                    @click="closeDeclineModal"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="btn btn-danger"
                    :disabled="decliningStudent"
                    @click="confirmDeclineStudent"
                >
                    {{ decliningStudent ? 'Declining...' : 'Confirm Decline' }}
                </button>
            </template>
        </Popup>

        <!-- Delete confirmation popup -->
        <Popup ref="deletePopup" title="Confirm Delete" @close="handleDeletePopupClose">
            <h3>Are you sure?</h3>

            <p>Are you sure you want to delete this student? This action cannot be undone.</p>

            <p class="studentDeleteName">
                {{ studentToDelete?.name }}
            </p>

            <template #buttons>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="deletingStudent"
                    @click="closeDeleteModal"
                >
                    Cancel
                </button>

                <button type="button" class="btn btn-danger" :disabled="deletingStudent" @click="confirmDeleteStudent">
                    {{ deletingStudent ? 'Deleting...' : 'Confirm Delete' }}
                </button>
            </template>
        </Popup>
    </div>
</template>

<style>
.studentTableWrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.studentTableWrapper .tableResponsive {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
}

.studentTableWrapper .tableResponsive table {
    width: 100%;
    min-width: 1000px;
    margin-bottom: 0;
}

.studentTableWrapper .tableResponsive th,
.studentTableWrapper .tableResponsive td {
    white-space: nowrap;
    vertical-align: middle;
}

.studentTableWrapper .tableResponsive td:last-child {
    min-width: 330px;
}

.studentStatusBadge {
    display: inline-block;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    line-height: 1.4;
}

.studentStatusActive {
    color: var(--colorSurface);
    background-color: var(--colorSuccess);
}

.studentStatusPending {
    color: var(--colorSurface);
    background-color: var(--colorWarning);
}

.studentStatusDisabled {
    color: var(--colorSurface);
    background-color: var(--colorTextSecondary);
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

.tableApproveButton,
.tableEnableButton {
    color: var(--colorSurface);
    background-color: var(--colorSuccess);
    border: 1px solid var(--colorSuccess);
}

.tableApproveButton:hover,
.tableEnableButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorSuccessHover);
    border-color: var(--colorSuccessHover);
}

.tableDisableButton {
    color: var(--colorWarning);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorWarning);
}

.tableDisableButton:hover {
    color: var(--colorSurface);
    background-color: var(--colorWarning);
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

.studentDeleteName {
    margin: var(--spaceXl) 0;
    font-weight: 600;
    text-align: center;
}
</style>
