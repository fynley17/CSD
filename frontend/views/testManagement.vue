<script setup>
// Import Vue functions for reactive state, computed values and lifecycle management.
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// Import the test table, create/edit form and reusable confirmation dialog.
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import TestManagementTable from '../components/tables/testManagementTable.vue';
import TestForm from '../components/forms/testForm.vue';
import PopupDialog from '../components/common/popupDialog.vue';

// Import the API functions used to retrieve the current user and delete tests.
import { getCurrentUser } from '../services/authService';
import { deleteTest } from '../services/testService';
import { nameOptions } from '../utils/filterOptions';

// Store the signed-in user so only lecturers and admins can create tests.
// Admins are lecturers too, so the test is saved with them as its creator.
const currentUser = ref(null);
const canCreateTest = computed(() => ['lecturer', 'admin'].includes(currentUser.value?.accountType));

// References to the child components.
const testManagementTable = ref(null);
const deleteDialog = ref(null);

// Store the current subject filter, the search box is the table's own.
const searchText = ref('');
const subjectFilter = ref('');

// Control whether the create/edit form is displayed and which test is being edited.
const showTestForm = ref(false);
const editingTest = ref(null);

// Store the test selected for deletion.
const testToDelete = ref(null);

// Track whether a test deletion is in progress.
const deletingTest = ref(false);

// Store success and error messages displayed to the lecturer.
const successMessage = ref('');
const errorMessage = ref('');

// Store the notification timeout so it can be cleared when necessary.
let messageTimeout = null;

// Retrieve the subject options exposed by the test management table.
const subjects = computed(() => testManagementTable.value?.subjects ?? []);

// Clear existing feedback and any pending notification timeout.
function clearMessages() {
    successMessage.value = '';
    errorMessage.value = '';

    if (messageTimeout) {
        clearTimeout(messageTimeout);
        messageTimeout = null;
    }
}

// Display a temporary success notification.
function showSuccess(message) {
    clearMessages();
    successMessage.value = message;

    messageTimeout = setTimeout(() => {
        successMessage.value = '';
        messageTimeout = null;
    }, 10000);
}

// Display a temporary error notification.
function showError(message) {
    clearMessages();
    errorMessage.value = message;

    messageTimeout = setTimeout(() => {
        errorMessage.value = '';
        messageTimeout = null;
    }, 10000);
}

// Pass the selected subject filter to the table component.
const filterBySubject = () => {
    testManagementTable.value?.filterBySubject(subjectFilter.value);
};

// Open the form in create mode and clear any previously selected test.
function openCreateForm() {
    clearMessages();
    editingTest.value = null;
    showTestForm.value = true;
}

// Open the form in edit mode and populate it with the selected test.
function openEditForm(test) {
    clearMessages();
    editingTest.value = test;
    showTestForm.value = true;
}

// Close the create/edit form and reset the selected test.
function closeTestForm() {
    showTestForm.value = false;
    editingTest.value = null;
}

// Refresh the table after a successful create or edit operation.
async function handleTestSaved() {
    const wasEditing = Boolean(editingTest.value);

    try {
        await testManagementTable.value?.refreshTests();

        showSuccess(wasEditing ? 'Test updated successfully.' : 'Test created successfully.');
    } catch {
        showError('Test saved, but the table could not be refreshed. Please reload the page.');
    }
}

// Store the selected test and open the deletion confirmation dialog.
async function openDeleteDialog(test) {
    clearMessages();
    testToDelete.value = test;

    // Wait for the dialog component to update before opening it.
    await nextTick();
    deleteDialog.value?.open();
}

// Clear the selected test when the deletion dialog closes.
function closeDeleteDialog() {
    testToDelete.value = null;
}

// Archive or delete the selected test after the lecturer confirms the action.
async function confirmDelete() {
    if (!testToDelete.value?.testID) {
        return;
    }

    deletingTest.value = true;
    clearMessages();

    try {
        // Send the request to the backend.
        const response = await deleteTest(testToDelete.value.testID);

        // Retrieve the message whether the service returns Axios response data
        // or the response data directly.
        const message = response?.data?.message ?? response?.message ?? 'Test processed successfully.';

        // Close the confirmation dialog and refresh the table.
        deleteDialog.value?.close();

        await testManagementTable.value?.refreshTests();

        // Display the backend's archive or deletion message.
        showSuccess(message);
    } catch (error) {
        // Display the backend error where available, otherwise use a fallback message.
        showError(
            error.response?.data?.error ??
                error.response?.data?.message ??
                'Unable to process the test. Please try again.',
        );
    } finally {
        deletingTest.value = false;
    }
}

// Retrieve the signed-in user's account type when the page loads.
onMounted(async () => {
    currentUser.value = await getCurrentUser();
});

// Clear any outstanding notification timeout when leaving the page.
onBeforeUnmount(() => {
    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }
});
</script>

<template>
    <!-- laid out like lecturer and subject management so every table page matches -->
    <section class="container py-4 adminPage">
        <h1>Test Management</h1>
        <p>Create, edit and manage tests</p>

        <!-- Display a temporary success notification after an operation. -->
        <div v-if="successMessage" class="toastMessage successMessage" role="status" aria-live="polite">
            <span aria-hidden="true">✓</span>
            {{ successMessage }}
        </div>

        <!-- Display a temporary error notification if an operation fails. -->
        <div v-if="errorMessage" class="toastMessage errorMessage" role="alert" aria-live="assertive">
            <span aria-hidden="true">!</span>
            {{ errorMessage }}
        </div>

        <!-- search and subject filter on the left, create button on the right -->
        <div class="tableToolbar">
            <div class="tableToolbarControls">
                <TableSearch id="testSearch" v-model="searchText" placeholder="Test name or subject" />
                <TableFilter
                    id="testSubjectFilter"
                    v-model="subjectFilter"
                    label="Subject"
                    :options="nameOptions(subjects, 'All subjects')"
                    @change="filterBySubject"
                />
            </div>
            <div class="tableToolbarActions">
                <button v-if="canCreateTest" type="button" class="btn btn-primary" @click="openCreateForm">
                    Create test
                </button>
            </div>
        </div>

        <TestManagementTable
            ref="testManagementTable"
            :search="searchText"
            @edit="openEditForm"
            @delete="openDeleteDialog"
        />

        <!-- Reuse the same form for creating and editing tests. -->
        <TestForm v-if="showTestForm" :test="editingTest" @saved="handleTestSaved" @close="closeTestForm" />

        <!-- Explain the archive and permanent deletion behaviour. -->
        <PopupDialog ref="deleteDialog" title="Archive / Delete Test" @close="closeDeleteDialog">
            <div class="d-flex flex-column gap-2">
                <p class="mb-0">
                    Are you sure you want to remove
                    <strong>{{ testToDelete?.testName }}</strong>
                    from the available tests?
                </p>

                <p class="mb-0">
                    If students have completed attempts, this test will be archived and all existing results and
                    leaderboard history will be preserved.
                </p>

                <p class="mb-0">If no completed attempts exist, the test will be permanently deleted.</p>
            </div>

            <!-- Provide cancel and confirm actions. -->
            <template #buttons>
                <button
                    type="button"
                    class="btn btn-outline-primary flex-fill"
                    :disabled="deletingTest"
                    @click="deleteDialog?.close()"
                >
                    Cancel
                </button>

                <button type="button" class="btn btn-danger flex-fill" :disabled="deletingTest" @click="confirmDelete">
                    {{ deletingTest ? 'Processing...' : 'Confirm' }}
                </button>
            </template>
        </PopupDialog>
    </section>
</template>

<style scoped>
/* Position temporary notifications in the top-right corner. */
.toastMessage {
    position: fixed;
    z-index: 1100;
    top: var(--spaceLg);
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

.successMessage {
    border-color: var(--colorPrimary);
}

.errorMessage {
    border-color: var(--colorError);
    color: var(--colorError);
}

@media (max-width: 639px) {
    .toastMessage {
        top: var(--spaceMd);
        right: var(--spaceMd);
    }
}
</style>
