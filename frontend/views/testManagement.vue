<script setup>
// Import Vue functions for reactive state, computed values and lifecycle management.
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

// Import the test table, create/edit form and reusable confirmation dialog.
import TestManagementTable from '../components/tables/testManagementTable.vue';
import TestForm from '../components/forms/testForm.vue';
import PopupDialog from '../components/common/popupDialog.vue';

// Import the API functions used to retrieve the current user and delete tests.
import { getCurrentUser } from '../services/authService';
import { deleteTest } from '../services/testService';

// Store the signed-in user so only lecturers can create tests.
const currentUser = ref(null);
const canCreateTest = computed(() => currentUser.value?.accountType === 'lecturer');

// References to the child components.
const testManagementTable = ref(null);
const deleteDialog = ref(null);

// Store the current subject filter and search input.
const subjectFilter = ref('');
const searchTerm = ref('');

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

// Pass the search term to the table component.
const searchTests = () => {
    testManagementTable.value?.searchTests(searchTerm.value);
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
    <main class="testManagement">
        <!-- Page heading and description. -->
        <div class="pageHeading">
            <div>
                <h1>Test Management</h1>
                <p>Create, edit and manage tests</p>
            </div>
        </div>

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

        <!-- Toolbar containing the subject filter and search input. -->
        <div class="tableToolbar">
            <label class="subjectFilter">
                <span class="visuallyHidden">Filter tests by subject</span>

                <select v-model="subjectFilter" @change="filterBySubject">
                    <option value="">All subjects</option>

                    <option v-for="subject in subjects" :key="subject" :value="subject">
                        {{ subject }}
                    </option>
                </select>
            </label>

            <label class="testSearch">
                <span class="visuallyHidden">Search tests</span>

                <input v-model="searchTerm" type="search" placeholder="Search tests..." @input="searchTests" />
            </label>
        </div>

        <!-- Display the test management table and its pagination. -->
        <div class="tableSection">
            <TestManagementTable ref="testManagementTable" @edit="openEditForm" @delete="openDeleteDialog" />

            <!-- Button to open the form for creating a new test. -->
            <div class="tableFooter">
                <button v-if="canCreateTest" type="button" class="primaryButton" @click="openCreateForm">
                    <span aria-hidden="true">＋</span>
                    Create New Test
                </button>
            </div>
        </div>

        <!-- Reuse the same form for creating and editing tests. -->
        <TestForm v-if="showTestForm" :test="editingTest" @saved="handleTestSaved" @close="closeTestForm" />

        <!-- Explain the archive and permanent deletion behaviour. -->
        <PopupDialog ref="deleteDialog" title="Archive / Delete Test" @close="closeDeleteDialog">
            <div class="deleteConfirmation">
                <p>
                    Are you sure you want to remove
                    <strong>{{ testToDelete?.testName }}</strong>
                    from the available tests?
                </p>

                <p>
                    If students have completed attempts, this test will be archived and all existing results and
                    leaderboard history will be preserved.
                </p>

                <p>If no completed attempts exist, the test will be permanently deleted.</p>
            </div>

            <!-- Provide cancel and confirm actions. -->
            <template #buttons>
                <button type="button" class="secondaryButton" :disabled="deletingTest" @click="deleteDialog?.close()">
                    Cancel
                </button>

                <button type="button" class="deleteButton" :disabled="deletingTest" @click="confirmDelete">
                    {{ deletingTest ? 'Processing...' : 'Confirm' }}
                </button>
            </template>
        </PopupDialog>
    </main>
</template>

<style scoped>
/* Main page layout and typography. */
.testManagement {
    max-width: var(--contentMaxWidth);
    min-height: 100%;
    margin: 0 auto;
    padding: var(--space2xl) var(--spaceLg);
    color: var(--colorText);
    font-family: var(--fontBody);
}

/* Page heading layout. */
.pageHeading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceMd);
    margin-bottom: var(--space2xl);
}

.pageHeading h1 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

.pageHeading p {
    margin: var(--spaceXs) 0 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

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

/* Success notification styling. */
.successMessage {
    border-color: var(--colorPrimary);
}

/* Error notification styling. */
.errorMessage {
    border-color: var(--colorError);
    color: var(--colorError);
}

/* Layout for the subject filter and search toolbar. */
.tableToolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceLg);
    margin-bottom: var(--spaceMd);
}

.subjectFilter,
.testSearch {
    flex: 1;
    min-width: 0;
}

.subjectFilter {
    max-width: 405px;
}

.testSearch {
    max-width: 320px;
    margin-left: auto;
}

/* Shared styling for toolbar input and select elements. */
.tableToolbar input,
.tableToolbar select {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    padding: var(--spaceSm);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font: inherit;
}

/* Table and create button layout. */
.tableSection {
    width: 100%;
}

.tableFooter {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--spaceMd);
}

/* Shared styling for management page buttons. */
.primaryButton,
.secondaryButton,
.deleteButton {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

/* Create button styling. */
.primaryButton {
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

/* Cancel button styling. */
.secondaryButton {
    background: var(--colorSurface);
    color: var(--colorPrimary);
}

/* Confirm button styling. */
.deleteButton {
    border-color: var(--colorError);
    background: var(--colorError);
    color: var(--colorSurface);
}

.deleteButton:hover {
    background: var(--colorErrorHover);
}

/* Indicate when an action is unavailable. */
.primaryButton:disabled,
.secondaryButton:disabled,
.deleteButton:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Arrange the deletion confirmation message. */
.deleteConfirmation {
    display: flex;
    flex-direction: column;
    gap: var(--spaceSm);
}

.deleteConfirmation p {
    margin: 0;
}

/* Provide visible focus indicators for keyboard navigation. */
button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

/* Hide labels visually while keeping them available to screen readers. */
.visuallyHidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}

/* Adjust the page layout for smaller screens. */
@media (max-width: 639px) {
    .testManagement {
        padding: var(--spaceLg) var(--spaceMd);
    }

    .pageHeading {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: var(--spaceXl);
    }

    .tableToolbar {
        flex-direction: column;
        align-items: stretch;
        gap: var(--spaceMd);
    }

    .subjectFilter,
    .testSearch {
        max-width: none;
    }

    .tableFooter .primaryButton {
        width: 100%;
    }

    .toastMessage {
        top: var(--spaceMd);
        right: var(--spaceMd);
    }
}
</style>
