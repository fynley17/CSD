<script setup>
import { computed, onMounted, ref } from 'vue';

import TestManagementTable from '../components/tables/testManagementTable.vue';
import TestForm from '../components/forms/testForm.vue';

import {
    getLecturerSubjects,
    createLecturerTest,
    updateLecturerTest,
    deleteLecturerTest,
} from '../services/lecturerService';

import api from '../services/api';

const testManagementTable = ref(null);

const subjectFilter = ref('');
const searchTerm = ref('');

const showTestModal = ref(false);
const showDeleteModal = ref(false);

const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const subjects = ref([]);
const cohorts = ref([]);

const selectedTest = ref(null);
const testToDelete = ref(null);

const tableSubjects = computed(() => testManagementTable.value?.subjects ?? []);

const isEditing = computed(() => selectedTest.value !== null);

// Load active subjects and available cohorts.
const loadFormOptions = async () => {
    try {
        const [subjectData, cohortResponse] = await Promise.all([getLecturerSubjects(), api.get('/cohorts')]);

        subjects.value = subjectData;
        cohorts.value = cohortResponse.data;
    } catch {
        errorMessage.value = 'Unable to load the test form options. Please try again.';
    }
};

onMounted(loadFormOptions);

// Filter the table by subject.
const filterBySubject = () => {
    testManagementTable.value?.filterBySubject(subjectFilter.value);
};

// Search the table.
const searchTests = () => {
    testManagementTable.value?.searchTests(searchTerm.value);
};

// Open the form to create a new test.
const openCreateModal = () => {
    selectedTest.value = null;
    errorMessage.value = '';
    successMessage.value = '';
    showTestModal.value = true;
};

// Open the form to edit an existing test.
const openEditModal = (test) => {
    selectedTest.value = { ...test };
    errorMessage.value = '';
    successMessage.value = '';
    showTestModal.value = true;
};

// Close the create/edit modal.
const closeTestModal = () => {
    if (loading.value) return;

    showTestModal.value = false;
    selectedTest.value = null;
    errorMessage.value = '';
};

// Create or update a test.
const saveTest = async (testData) => {
    loading.value = true;
    errorMessage.value = '';

    try {
        if (isEditing.value) {
            await updateLecturerTest(selectedTest.value.testID, testData);

            successMessage.value = 'Test updated successfully.';
        } else {
            await createLecturerTest(testData);

            successMessage.value = 'Test created successfully.';
        }

        showTestModal.value = false;
        selectedTest.value = null;

        await testManagementTable.value?.refreshTests?.();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Unable to save the test. Please try again.';
    } finally {
        loading.value = false;
    }
};

// Open the delete confirmation modal.
const openDeleteModal = (test) => {
    testToDelete.value = test;
    errorMessage.value = '';
    successMessage.value = '';
    showDeleteModal.value = true;
};

// Close the delete confirmation modal.
const closeDeleteModal = () => {
    if (loading.value) return;

    showDeleteModal.value = false;
    testToDelete.value = null;
    errorMessage.value = '';
};

// Delete the selected test.
const confirmDelete = async () => {
    if (!testToDelete.value) return;

    loading.value = true;
    errorMessage.value = '';

    try {
        await deleteLecturerTest(testToDelete.value.testID);

        successMessage.value = 'Test deleted successfully.';

        showDeleteModal.value = false;
        testToDelete.value = null;

        await testManagementTable.value?.refreshTests?.();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Unable to delete the test. Please try again.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <main class="testManagement">
        <div class="pageHeading">
            <div>
                <h1>Test Management</h1>
                <p>Create, edit and manage tests</p>
            </div>
        </div>

        <!-- Success notification. -->
        <div v-if="successMessage" class="successMessage" role="status">
            <span>{{ successMessage }}</span>

            <button type="button" aria-label="Dismiss notification" @click="successMessage = ''">&times;</button>
        </div>

        <!-- Page-level error notification. -->
        <div v-if="errorMessage && !showTestModal && !showDeleteModal" class="pageError" role="alert">
            {{ errorMessage }}
        </div>

        <!-- Toolbar containing the subject filter and search input. -->
        <div class="tableToolbar">
            <label class="subjectFilter">
                <span class="visuallyHidden">Filter tests by subject</span>

                <select v-model="subjectFilter" @change="filterBySubject">
                    <option value="">All subjects</option>

                    <option v-for="subject in tableSubjects" :key="subject" :value="subject">
                        {{ subject }}
                    </option>
                </select>
            </label>

            <label class="testSearch">
                <span class="visuallyHidden">Search tests</span>

                <input v-model="searchTerm" type="search" placeholder="Search tests..." @input="searchTests" />
            </label>
        </div>

        <!-- Test management table. -->
        <div class="tableSection">
            <TestManagementTable ref="testManagementTable" @edit="openEditModal" @delete="openDeleteModal" />

            <div class="tableFooter">
                <button type="button" class="primaryButton" @click="openCreateModal">
                    <span aria-hidden="true">＋</span>
                    Create New Test
                </button>
            </div>
        </div>

        <Teleport to="body">
            <!-- Create and Edit modal. -->
            <div v-if="showTestModal" class="modalOverlay">
                <section class="modalContent" role="dialog" aria-modal="true" aria-labelledby="testModalTitle">
                    <div class="modalHeader">
                        <div class="modalHeadingText">
                            <h2 id="testModalTitle">
                                {{ isEditing ? 'Edit Test' : 'Add Test' }}
                            </h2>
                            <p>
                                {{
                                    isEditing
                                        ? 'Update the test details and settings below.'
                                        : 'Set up the test details and settings below.'
                                }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="closeButton"
                            aria-label="Close modal"
                            :disabled="loading"
                            @click="closeTestModal"
                        >
                            &times;
                        </button>
                    </div>

                    <div v-if="errorMessage" class="modalError" role="alert">
                        {{ errorMessage }}
                    </div>

                    <TestForm
                        :subjects="subjects"
                        :cohorts="cohorts"
                        :test="selectedTest"
                        :loading="loading"
                        @save="saveTest"
                        @cancel="closeTestModal"
                    />
                </section>
            </div>

            <!-- Delete confirmation modal. -->
            <div v-if="showDeleteModal" class="modalOverlay">
                <section
                    class="modalContent deleteModal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="deleteModalTitle"
                >
                    <div class="modalHeader">
                        <h2 id="deleteModalTitle">Delete Test</h2>

                        <button
                            type="button"
                            class="closeButton"
                            aria-label="Close modal"
                            :disabled="loading"
                            @click="closeDeleteModal"
                        >
                            &times;
                        </button>
                    </div>

                    <div v-if="errorMessage" class="modalError" role="alert">
                        {{ errorMessage }}
                    </div>

                    <p>
                        Are you sure you want to delete
                        <strong>{{ testToDelete?.testName }}</strong
                        >?
                    </p>

                    <p class="deleteWarning">
                        This action cannot be undone. Tests with completed student attempts cannot be deleted.
                        Historical student results will be protected.
                    </p>

                    <div class="formActions">
                        <button type="button" class="cancelButton" :disabled="loading" @click="closeDeleteModal">
                            Cancel
                        </button>

                        <button type="button" class="deleteConfirmButton" :disabled="loading" @click="confirmDelete">
                            {{ loading ? 'Deleting...' : 'Delete Test' }}
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
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
.cancelButton,
.deleteConfirmButton {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.primaryButton {
    border: 1px solid var(--colorPrimary);
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

.successMessage,
.pageError {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--spaceMd);
    padding: var(--spaceSm) var(--spaceMd);
    margin-bottom: var(--spaceMd);
    border: 1px solid var(--colorSuccess);
    border-radius: var(--radiusSm);
    color: var(--colorSuccess);
    background: var(--colorSurface);
}

.pageError {
    border-color: var(--colorError);
    color: var(--colorError);
}

.successMessage button {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: var(--fontSizeH2);
    cursor: pointer;
}

/* Modal overlay starts below the navbar. */
.modalOverlay {
    position: fixed;
    inset: 66px 0 0;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--spaceLg);
    overflow-y: auto;
}

.modalOverlay::before {
    position: fixed;
    z-index: 0;
    inset: 66px 0 0;
    background: var(--colorText);
    opacity: 0.65;
    content: '';
    pointer-events: none;
}

/* Create and Edit modal. */
.modalContent {
    width: 100%;
    max-width: 760px;
    position: relative;
    z-index: 1;
    max-height: calc(100vh - 100px);
    padding: var(--spaceLg);
    overflow-y: auto;
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
}

/* Smaller Delete modal. */
.deleteModal {
    max-width: 440px;
}

.modalHeader {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--spaceMd);
    margin-bottom: var(--spaceXl);
    padding: 0;
    border: none;
}

.modalHeadingText {
    min-width: 0;
}

.modalHeader h2 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

.modalHeadingText p {
    margin: var(--spaceXs) 0 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
    line-height: 1.5;
}

.closeButton {
    display: flex;
    align-items: center;
    justify-content: center;
    width: var(--space2xl);
    height: var(--space2xl);
    border: 0;
    border-radius: var(--radiusSm);
    background: transparent;
    color: var(--colorText);
    font-size: var(--fontSizeH2);
    cursor: pointer;
}

.closeButton:hover {
    background: var(--colorBorderInput);
}

.closeButton:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.modalError {
    margin-bottom: var(--spaceMd);
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorError);
    border-radius: var(--radiusSm);
    color: var(--colorError);
    background: var(--colorSurface);
}

.deleteModal p {
    line-height: 1.55;
}

.deleteWarning {
    padding: var(--spaceSm) var(--spaceMd);
    border-radius: var(--radiusSm);
    background: var(--colorSurfaceAlt);
    color: var(--colorTextMuted);
    font-size: var(--fontSizeSmall);
}

.formActions {
    display: flex;
    justify-content: flex-end;
    gap: var(--spaceSm);
    margin-top: var(--spaceLg);
}

.cancelButton {
    color: var(--colorTextSecondary);
    background: var(--colorSurface);
    border: 1px solid var(--colorBorderInput);
}

.cancelButton:hover:not(:disabled) {
    background: var(--colorBorderInput);
}

.deleteConfirmButton {
    color: var(--colorSurface);
    background: var(--colorError);
    border: 1px solid var(--colorError);
}

.deleteConfirmButton:hover:not(:disabled) {
    background: var(--colorErrorHover);
}

button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

.formActions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

    .modalOverlay {
        padding: var(--spaceSm);
    }

    .modalContent {
        max-height: calc(100vh - 85px);
        padding: var(--spaceMd);
    }

    .formActions {
        flex-direction: column-reverse;
    }

    .formActions button {
        width: 100%;
    }
}
</style>
