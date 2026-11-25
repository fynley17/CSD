<!-- Cohort management, where admins look after cohorts and who's in them -->
<template>
    <section class="cohortManagement">
        <h1>Cohort Management</h1>
        <p class="cohortManagementIntro">
            Create and manage cohorts, the students in them and the subjects they study. Every student has to be in a
            cohort, so taking a student out of one means moving them to another
        </p>

        <!-- one search for both tables, as they list the same cohorts -->
        <div class="tableToolbar">
            <div class="tableToolbarControls">
                <TableSearch id="cohortSearch" v-model="searchText" placeholder="Cohort name" />
                <TableFilter
                    id="cohortStatusFilter"
                    v-model="statusFilter"
                    label="Status"
                    :options="cohortStatusOptions"
                />
                <TableFilter id="cohortSort" v-model="sortBy" label="Sort by" :options="sortOptions" />
            </div>

            <div class="tableToolbarActions">
                <button type="button" class="btn btn-primary cohortCreateButton" @click="openCohortForm(null)">
                    <span aria-hidden="true">+</span> Create Cohort
                </button>
            </div>
        </div>

        <div v-if="successMessage" class="toastMessage successMessage" role="status" aria-live="polite">
            <span aria-hidden="true">✓</span>
            <span>{{ successMessage }}</span>
            <button
                v-if="undoCohort"
                type="button"
                class="btn btn-sm btn-outline-primary toastUndo"
                :aria-label="'Undo for ' + undoCohort.cohortName"
                @click="undo"
            >
                Undo
            </button>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeSuccess">×</button>
        </div>

        <div
            v-if="errorMessage"
            ref="errorToast"
            popover="manual"
            class="toastMessage errorMessage"
            role="alert"
            aria-live="assertive"
        >
            <span aria-hidden="true">!</span>
            <span>{{ errorMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeError">×</button>
        </div>

        <p v-if="isLoading" class="text-center py-4 mb-0" role="status">Loading cohorts</p>

        <div v-else-if="loadError" class="alert alert-danger mt-3 mb-0" role="alert">
            <strong>There is a problem:</strong> {{ loadError }}
        </div>

        <template v-else>
            <h2>Students in each cohort</h2>
            <p class="cohortManagementIntro">
                Press the arrow next to a cohort to show its details, where you can add students or move a student to
                another cohort
            </p>
            <CohortStudentsTable
                :cohorts="visibleCohorts"
                :toolbarSort="sortBy"
                @edit="openCohortForm"
                @toggleStatus="startToggle"
                @delete="deletingCohort = $event"
                @addStudents="addingToCohort = $event"
                @moveStudent="(cohort, student) => (moving = { cohort, student })"
            />

            <h2>Subjects for each cohort</h2>
            <p class="cohortManagementIntro">
                Press the arrow next to a cohort to show its details, where you can assign or remove subjects
            </p>
            <CohortSubjectsTable
                :cohorts="visibleCohorts"
                :toolbarSort="sortBy"
                @assign="assigningCohort = $event"
                @remove="(cohort, subject) => (removing = { cohort, subject })"
            />
        </template>

        <AddStudentsDialog
            v-if="addingToCohort"
            :cohort="addingToCohort"
            @saved="handleStudentsAdded"
            @error="showError"
            @close="addingToCohort = null"
        />

        <MoveStudentDialog
            v-if="moving"
            :cohort="moving.cohort"
            :student="moving.student"
            :cohorts="allCohorts"
            @saved="handleStudentMoved"
            @error="showError"
            @close="moving = null"
        />

        <CohortFormDialog
            v-if="isCohortFormOpen"
            :cohort="editingCohort"
            @saved="saveCohort"
            @error="showError"
            @close="isCohortFormOpen = false"
        />

        <AssignSubjectsDialog
            v-if="assigningCohort"
            :cohort="assigningCohort"
            @saved="handleSubjectsAssigned"
            @error="showError"
            @close="assigningCohort = null"
        />

        <RemoveSubjectDialog
            v-if="removing"
            :cohort="removing.cohort"
            :subject="removing.subject"
            @saved="handleSubjectRemoved"
            @error="showError"
            @close="removing = null"
        />

        <!-- house style wants a pop-up before any disable, enable goes straight through -->
        <DeleteCohortDialog
            v-if="disablingCohort"
            :cohort="disablingCohort"
            isDisabling
            @disabled="cohortDisabled"
            @error="showError"
            @close="disablingCohort = null"
        />

        <DeleteCohortDialog
            v-if="deletingCohort"
            :cohort="deletingCohort"
            @deleted="removeCohort"
            @disabled="updateCohort(deletingCohort, $event)"
            @error="showError"
            @close="deletingCohort = null"
        />
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AddStudentsDialog from '../components/common/addStudentsDialog.vue';
import AssignSubjectsDialog from '../components/common/assignSubjectsDialog.vue';
import CohortFormDialog from '../components/common/cohortFormDialog.vue';
import DeleteCohortDialog from '../components/common/deleteCohortDialog.vue';
import MoveStudentDialog from '../components/common/moveStudentDialog.vue';
import RemoveSubjectDialog from '../components/common/removeSubjectDialog.vue';
import CohortStudentsTable from '../components/tables/cohortStudentsTable.vue';
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import { cohortStatusOptions } from '../utils/filterOptions';
import CohortSubjectsTable from '../components/tables/cohortSubjectsTable.vue';
import { getCohorts, setCohortActive } from '../services/cohortService';

const successMessage = ref('');
// the cohort that was just disabled, Undo shows in the green box while this is set
const undoCohort = ref(null);
const errorMessage = ref('');
const errorToast = ref(null);
let messageTimeout = null;
const allCohorts = ref([]);
const isLoading = ref(true);
const loadError = ref('');

const searchText = ref('');
const statusFilter = ref('');
const sortBy = ref('');
// which pop-up is open, null when it's closed
const addingToCohort = ref(null);
const moving = ref(null);
const isCohortFormOpen = ref(false);
const editingCohort = ref(null);
const assigningCohort = ref(null);
const removing = ref(null);
const deletingCohort = ref(null);
const disablingCohort = ref(null);

const sortOptions = [
    { value: '', label: 'Default order' },
    { value: 'nameAsc', label: 'Name (A-Z)' },
    { value: 'nameDesc', label: 'Name (Z-A)' },
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
];

const sorters = {
    nameAsc: (a, b) => a.cohortName.localeCompare(b.cohortName),
    nameDesc: (a, b) => b.cohortName.localeCompare(a.cohortName),
    // the dates are YYYY-MM-DD, so they sort correctly as text
    newest: (a, b) => b.createdDate.localeCompare(a.createdDate),
    oldest: (a, b) => a.createdDate.localeCompare(b.createdDate),
};

// the search, filter and sort apply to both tables
const visibleCohorts = computed(() => {
    const search = searchText.value.trim().toLowerCase();
    const cohorts = allCohorts.value.filter(
        (cohort) =>
            cohort.cohortName.toLowerCase().includes(search) &&
            (statusFilter.value === '' || cohort.isActive === (statusFilter.value === 'active')),
    );

    return sortBy.value ? cohorts.sort(sorters[sortBy.value]) : cohorts;
});

function showSuccess(message, cohort = null) {
    errorMessage.value = '';
    undoCohort.value = cohort;
    if (typeof errorToast.value?.hidePopover === 'function') {
        errorToast.value.hidePopover();
    }
    successMessage.value = message;

    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }

    messageTimeout = setTimeout(() => {
        successMessage.value = '';
        messageTimeout = null;
    }, 15000);
}

async function showError(message) {
    successMessage.value = '';
    errorMessage.value = message;
    if (messageTimeout) clearTimeout(messageTimeout);

    await nextTick();
    if (typeof errorToast.value?.showPopover === 'function' && !errorToast.value.matches(':popover-open')) {
        errorToast.value.showPopover();
    }

    messageTimeout = setTimeout(() => {
        if (typeof errorToast.value?.hidePopover === 'function') {
            errorToast.value.hidePopover();
        }
        errorMessage.value = '';
        messageTimeout = null;
    }, 15000);
}

function closeSuccess() {
    successMessage.value = '';
    if (messageTimeout) {
        clearTimeout(messageTimeout);
        messageTimeout = null;
    }
}

function closeError() {
    errorMessage.value = '';
    if (messageTimeout) {
        clearTimeout(messageTimeout);
        messageTimeout = null;
    }
    if (typeof errorToast.value?.hidePopover === 'function') {
        errorToast.value.hidePopover();
    }
}

onBeforeUnmount(() => {
    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }
});
onMounted(async () => {
    try {
        allCohorts.value = await getCohorts();
    } catch (error) {
        // PHP's message if it sent one, otherwise a plain one
        loadError.value = error.response?.data?.error || 'Unable to load the cohorts';
    } finally {
        isLoading.value = false;
    }
});

// changed in place rather than replaced, so the table stays on the same page with the same rows open
function updateCohort(cohort, savedCohort) {
    Object.assign(cohort, savedCohort);
}

// moving students changes two cohorts, so PHP sends them all and each is updated in place
function updateCohorts(savedCohorts) {
    savedCohorts.forEach((savedCohort) => {
        const cohort = allCohorts.value.find((current) => current.cohortID === savedCohort.cohortID);
        if (cohort) {
            updateCohort(cohort, savedCohort);
        }
    });
}

// null creates a new cohort
function openCohortForm(cohort) {
    editingCohort.value = cohort;
    isCohortFormOpen.value = true;
}

// a new cohort goes at the top of the list so it can be seen straight away
function saveCohort(savedCohort) {
    const wasEditing = Boolean(editingCohort.value);

    if (wasEditing) {
        updateCohort(editingCohort.value, savedCohort);
    } else {
        allCohorts.value = [savedCohort, ...allCohorts.value];
    }

    showSuccess(wasEditing ? 'Cohort updated successfully.' : 'Cohort created successfully.');
}

function handleStudentsAdded(savedCohorts) {
    updateCohorts(savedCohorts);
    showSuccess('Students added successfully.');
}

function handleStudentMoved(savedCohorts) {
    updateCohorts(savedCohorts);
    showSuccess('Student moved successfully.');
}

function handleSubjectsAssigned(savedCohort) {
    updateCohort(assigningCohort.value, savedCohort);
    showSuccess('Subjects assigned successfully.');
}

function handleSubjectRemoved(savedCohort) {
    updateCohort(removing.value.cohort, savedCohort);
    showSuccess('Subject removed successfully.');
}

function removeCohort(deletedCohort) {
    allCohorts.value = allCohorts.value.filter((cohort) => cohort.cohortID !== deletedCohort.cohortID);
    showSuccess('Cohort deleted successfully.');
}

// disable asks first, enable goes straight through
function startToggle(cohort) {
    closeError();
    if (cohort.isActive) {
        disablingCohort.value = cohort;
    } else {
        toggleStatus(cohort);
    }
}

function cohortDisabled(savedCohort) {
    updateCohort(disablingCohort.value, savedCohort);
    showSuccess('Cohort disabled successfully.', disablingCohort.value);
}

// turns it back on, toggleStatus shows its own message with no Undo so you cant undo the undo
function undo() {
    const cohort = undoCohort.value;
    undoCohort.value = null;
    toggleStatus(cohort);
}

// the screen only changes once PHP confirms it's saved, so it never shows a status the database doesn't have
async function toggleStatus(cohort) {
    closeError();
    const isActive = !cohort.isActive;

    try {
        Object.assign(cohort, await setCohortActive(cohort.cohortID, isActive));
        showSuccess(`Cohort ${isActive ? 'enabled' : 'disabled'} successfully.`);
    } catch (error) {
        showError(error.response?.data?.error || `Unable to ${isActive ? 'enable' : 'disable'} ${cohort.cohortName}`);
    }
}
</script>

<style scoped>
.cohortManagement {
    max-width: var(--contentMaxWidth);
    margin: 0 auto;
    padding: var(--spaceLg) var(--spaceMd);
    color: var(--colorText);
}

.cohortManagement h1 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

.cohortManagement h2 {
    margin: var(--spaceXl) 0 var(--spaceMd);
    color: var(--colorText);
    font-size: var(--fontSizeH3);
    font-weight: 700;
}

.cohortManagementIntro {
    margin: var(--spaceXs) 0 var(--spaceLg);
    color: var(--colorTextSecondary);
}

.toastMessage {
    position: fixed;
    z-index: 1100;
    top: calc(var(--navHeight) + var(--spaceLg));
    right: var(--spaceLg);
    display: flex;
    align-items: center;
    gap: var(--spaceSm);
    width: max-content;
    max-width: min(450px, calc(100vw - 2 * var(--spaceLg)));
    padding: var(--spaceMd) var(--spaceLg);
    border: 2px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font-weight: 600;
}

.toastClose {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: inherit;
    font-size: var(--fontSizeH3);
    line-height: 1;
    cursor: pointer;
}

.successMessage {
    border-color: var(--colorPrimary);
}

@media (min-width: 768px) {
    .cohortManagement {
        padding: var(--space2xl) var(--spaceLg);
    }
}

.errorMessage {
    border-color: var(--colorError);
    color: var(--colorError);
    margin: 0;
}

.errorMessage:popover-open {
    position: fixed;
    inset: calc(var(--navHeight) + var(--spaceLg)) var(--spaceLg) auto auto;
}

@media (max-width: 639px) {
    .errorMessage:popover-open {
        inset: calc(var(--navHeight) + var(--spaceMd)) var(--spaceMd) auto auto;
    }

    .toastMessage {
        top: calc(var(--navHeight) + var(--spaceMd));
        right: var(--spaceMd);
    }
}
</style>
