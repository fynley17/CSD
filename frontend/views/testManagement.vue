<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

import TestManagementTable from '../components/tables/testManagementTable.vue';
import TestForm from '../components/forms/testForm.vue';
import PopupDialog from '../components/common/popupDialog.vue';
import { deleteTest } from '../services/testService';

const testManagementTable = ref(null);
const deleteDialog = ref(null);

const subjectFilter = ref('');
const searchTerm = ref('');

const showTestForm = ref(false);
const editingTest = ref(null);
const testToDelete = ref(null);

const deletingTest = ref(false);

const successMessage = ref('');
const errorMessage = ref('');

let messageTimeout = null;

const subjects = computed(() => testManagementTable.value?.subjects ?? []);

// Clear existing feedback and any pending timeout.
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

const filterBySubject = () => {
    testManagementTable.value?.filterBySubject(subjectFilter.value);
};

const searchTests = () => {
    testManagementTable.value?.searchTests(searchTerm.value);
};

function openCreateForm() {
    clearMessages();
    editingTest.value = null;
    showTestForm.value = true;
}

function openEditForm(test) {
    clearMessages();
    editingTest.value = test;
    showTestForm.value = true;
}

function closeTestForm() {
    showTestForm.value = false;
    editingTest.value = null;
}

// Refresh the table after a successful operation.
async function handleTestSaved() {
    const wasEditing = Boolean(editingTest.value);

    try {
        await testManagementTable.value?.refreshTests();

        showSuccess(wasEditing ? 'Test updated successfully.' : 'Test created successfully.');
    } catch {
        showError('Test saved, but the table could not be refreshed. Please reload the page.');
    }
}

async function openDeleteDialog(test) {
    clearMessages();
    testToDelete.value = test;

    await nextTick();
    deleteDialog.value?.open();
}

function closeDeleteDialog() {
    testToDelete.value = null;
}

async function confirmDelete() {
    if (!testToDelete.value?.testID) {
        return;
    }

    deletingTest.value = true;
    clearMessages();

    try {
        await deleteTest(testToDelete.value.testID);

        deleteDialog.value?.close();

        await testManagementTable.value?.refreshTests();

        showSuccess('Test deleted successfully.');
    } catch (error) {
        showError(
            error.response?.data?.error ??
                error.response?.data?.message ??
                'Unable to delete the test. Please try again.',
        );
    } finally {
        deletingTest.value = false;
    }
}

// Clear any outstanding timeout when leaving the page.
onBeforeUnmount(() => {
    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }
});
</script>

<template>
    <main class="testManagement">
        <div class="pageHeading">
            <div>
                <h1>Test Management</h1>
                <p>Create, edit and manage tests</p>
            </div>
        </div>

        <!-- Temporary success notification. -->
        <div v-if="successMessage" class="toastMessage successMessage" role="status" aria-live="polite">
            <span aria-hidden="true">✓</span>
            {{ successMessage }}
        </div>

        <!-- Temporary error notification. -->
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

            <div class="tableFooter">
                <button type="button" class="primaryButton" @click="openCreateForm">
                    <span aria-hidden="true">＋</span>
                    Create New Test
                </button>
            </div>
        </div>

        <!-- Create or edit a test using the shared form. -->
        <TestForm v-if="showTestForm" :test="editingTest" @saved="handleTestSaved" @close="closeTestForm" />

        <!-- Confirm permanent test deletion. -->
        <PopupDialog ref="deleteDialog" title="Delete Test" @close="closeDeleteDialog">
            <div class="deleteConfirmation">
                <p>
                    Are you sure you want to permanently delete
                    <strong>{{ testToDelete?.testName }}</strong
                    >?
                </p>

                <p>
                    This will also delete all completed attempts associated with this test. This action cannot be
                    undone.
                </p>
            </div>

            <template #buttons>
                <button type="button" class="secondaryButton" :disabled="deletingTest" @click="deleteDialog?.close()">
                    Cancel
                </button>

                <button type="button" class="deleteButton" :disabled="deletingTest" @click="confirmDelete">
                    {{ deletingTest ? 'Deleting...' : 'Delete Test' }}
                </button>
            </template>
        </PopupDialog>
    </main>
</template>

<style scoped>
.testManagement {
    max-width: var(--contentMaxWidth);
    min-height: 100%;
    margin: 0 auto;
    padding: var(--space2xl) var(--spaceLg);
    color: var(--colorText);
    font-family: var(--fontBody);
}

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

.tableSection {
    width: 100%;
}

.tableFooter {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--spaceMd);
}

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

.primaryButton {
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

.secondaryButton {
    background: var(--colorSurface);
    color: var(--colorPrimary);
}

.deleteButton {
    border-color: var(--colorError);
    background: var(--colorError);
    color: var(--colorSurface);
}

.deleteButton:hover {
    background: var(--colorErrorHover);
}

.primaryButton:disabled,
.secondaryButton:disabled,
.deleteButton:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.deleteConfirmation {
    display: flex;
    flex-direction: column;
    gap: var(--spaceSm);
}

.deleteConfirmation p {
    margin: 0;
}

button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

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
