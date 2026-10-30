<script setup>
// Import Vue functions for reactive state and lifecycle management.
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';

// Import the question table and reusable question form.
import QuestionManagementTable from '../components/tables/questionManagementTable.vue';
import QuestionForm from '../components/forms/questionForm.vue';

// Import the services used to manage questions and subjects.
import { getActiveSubjects, createQuestion, updateQuestion, deleteQuestion } from '../services/questionService';

const questionTable = ref(null);
const availableSubjects = ref([]);

const subjectFilter = ref('');
const searchTerm = ref('');

const showQuestionForm = ref(false);
const selectedQuestion = ref(null);

const saving = ref(false);

const successMessage = ref('');
const errorMessage = ref('');

const questionToDelete = ref(null);
const showDeleteConfirmation = ref(false);

// Store the notification timeout.
let messageTimeout = null;

// Retrieve the subject names from the question table.
const subjects = computed(() => questionTable.value?.subjects ?? []);

// Retrieve the active subjects for the question form.
const formSubjects = computed(() => availableSubjects.value);

// Load the active subjects when the page is opened.
onMounted(async () => {
    try {
        availableSubjects.value = await getActiveSubjects();
    } catch (error) {
        showError(error.response?.data?.error ?? 'Unable to load subjects. Please try again.');
    }
});

// Clear existing feedback and any pending timeout.
const clearMessages = () => {
    successMessage.value = '';
    errorMessage.value = '';

    if (messageTimeout) {
        clearTimeout(messageTimeout);
        messageTimeout = null;
    }
};

// Display a temporary success notification.
const showSuccess = (message) => {
    clearMessages();
    successMessage.value = message;

    messageTimeout = setTimeout(() => {
        successMessage.value = '';
        messageTimeout = null;
    }, 3000);
};

// Display a temporary error notification.
const showError = (message) => {
    clearMessages();
    errorMessage.value = message;

    messageTimeout = setTimeout(() => {
        errorMessage.value = '';
        messageTimeout = null;
    }, 5000);
};

// Apply the selected subject filter.
const filterBySubject = () => {
    questionTable.value?.filterBySubject(subjectFilter.value);
};

// Search the questions using the DataTables API.
const searchQuestions = () => {
    questionTable.value?.searchQuestions(searchTerm.value);
};

// Open the form to create a new question.
const openCreateForm = () => {
    clearMessages();
    selectedQuestion.value = null;
    showQuestionForm.value = true;
};

// Open the form to edit an existing question.
const openEditForm = (question) => {
    clearMessages();
    selectedQuestion.value = question;
    showQuestionForm.value = true;
};

// Close the create/edit form.
const closeQuestionForm = () => {
    showQuestionForm.value = false;
    selectedQuestion.value = null;
};

// Refresh the question table after a successful operation.
const refreshQuestions = async () => {
    await questionTable.value?.refreshQuestions();
};

// Save a new question or update an existing question.
const saveQuestion = async (questionData) => {
    saving.value = true;
    clearMessages();

    try {
        if (selectedQuestion.value) {
            await updateQuestion(selectedQuestion.value.questionID, questionData);
            closeQuestionForm();
            await refreshQuestions();

            showSuccess('Question updated successfully.');
        } else {
            await createQuestion(questionData);
            closeQuestionForm();
            await refreshQuestions();

            showSuccess('Question created successfully.');
        }
    } catch (error) {
        showError(error.response?.data?.error ?? 'Unable to save the question. Please try again.');
    } finally {
        saving.value = false;
    }
};

// Open the delete confirmation modal.
const openDeleteConfirmation = (question) => {
    clearMessages();
    questionToDelete.value = question;
    showDeleteConfirmation.value = true;
};

// Close the delete confirmation modal.
const closeDeleteConfirmation = () => {
    showDeleteConfirmation.value = false;
    questionToDelete.value = null;
};

// Delete the selected question after confirmation.
const confirmDelete = async () => {
    if (!questionToDelete.value) {
        return;
    }

    saving.value = true;
    clearMessages();

    try {
        await deleteQuestion(questionToDelete.value.questionID);

        closeDeleteConfirmation();
        await refreshQuestions();

        showSuccess('Question deleted successfully.');
    } catch (error) {
        showError(error.response?.data?.error ?? 'Unable to delete the question. Please try again.');
    } finally {
        saving.value = false;
    }
};

// Clear any outstanding timeout when leaving the page.
onBeforeUnmount(() => {
    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }
});
</script>

<template>
    <main class="questionManagement">
        <div class="pageHeading">
            <div>
                <h1>Question Management</h1>
                <p>Create, edit and manage questions</p>
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

        <!-- Subject filter and search. -->
        <div class="tableToolbar">
            <label class="subjectFilter">
                <span class="visuallyHidden">Filter questions by subject</span>

                <select v-model="subjectFilter" @change="filterBySubject">
                    <option value="">All subjects</option>

                    <option v-for="subject in subjects" :key="subject" :value="subject">
                        {{ subject }}
                    </option>
                </select>
            </label>

            <label class="questionSearch">
                <span class="visuallyHidden">Search questions</span>

                <input v-model="searchTerm" type="search" placeholder="Search questions..." @input="searchQuestions" />
            </label>
        </div>

        <!-- Questions table. -->
        <div class="tableSection">
            <QuestionManagementTable
                ref="questionTable"
                @editQuestion="openEditForm"
                @deleteQuestion="openDeleteConfirmation"
            />

            <div class="tableFooter">
                <button type="button" class="primaryButton" @click="openCreateForm">
                    <span aria-hidden="true">＋</span>
                    Create New Question
                </button>
            </div>
        </div>

        <!-- Create/Edit question modal. -->
        <div v-if="showQuestionForm" class="modalOverlay">
            <section class="modalContent" role="dialog" aria-modal="true" aria-label="Question form">
                <QuestionForm
                    :subjects="formSubjects"
                    :question="selectedQuestion"
                    :loading="saving"
                    @save="saveQuestion"
                    @cancel="closeQuestionForm"
                />
            </section>
        </div>

        <!-- Delete confirmation modal. -->
        <div v-if="showDeleteConfirmation" class="modalOverlay">
            <section
                class="modalContent deleteModal"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="deleteModalTitle"
            >
                <h2 id="deleteModalTitle">Are you sure?</h2>

                <p>Are you sure you want to delete this question? This action cannot be undone.</p>

                <div class="questionToDelete">
                    {{ questionToDelete?.question }}
                </div>

                <div class="modalActions">
                    <button type="button" class="cancelButton" :disabled="saving" @click="closeDeleteConfirmation">
                        Cancel
                    </button>

                    <button type="button" class="deleteConfirmButton" :disabled="saving" @click="confirmDelete">
                        {{ saving ? 'Deleting...' : 'Confirm Delete' }}
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
.questionManagement {
    width: 100%;
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
    border: 1px solid var(--colorBorder);
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
.questionSearch {
    flex: 1;
    min-width: 0;
}

.subjectFilter {
    max-width: 405px;
}

.questionSearch {
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
    min-width: 0;
}

.tableFooter {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--spaceMd);
}

.primaryButton {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    background: var(--colorPrimary);
    color: var(--colorSurface);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

.modalOverlay {
    position: fixed;
    z-index: 1000;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--spaceLg);
    overflow-y: auto;
    background: var(--colorText);
}

.modalContent {
    width: 100%;
    max-width: 650px;
    max-height: 90vh;
    padding: var(--spaceXl);
    overflow-y: auto;
    border-radius: var(--radiusMd);
    background: var(--colorSurface);
    color: var(--colorText);
}

.deleteModal h2 {
    margin-top: 0;
    font-family: var(--fontHeading);
    color: var(--colorText);
}

.deleteModal > p {
    color: var(--colorTextSecondary);
    line-height: 1.5;
}

.questionToDelete {
    margin: var(--spaceMd) 0;
    padding: var(--spaceMd);
    border: 1px solid var(--colorBorder);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    font-weight: 600;
}

.modalActions {
    display: flex;
    justify-content: flex-end;
    gap: var(--spaceSm);
    margin-top: var(--spaceLg);
}

.modalActions button {
    min-height: 40px;
    padding: var(--spaceSm) var(--spaceMd);
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.cancelButton {
    border: 1px solid var(--colorBorder);
    background: var(--colorSurface);
    color: var(--colorText);
}

.deleteConfirmButton {
    border: 1px solid var(--colorError);
    background: var(--colorError);
    color: var(--colorSurface);
}

button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

.modalActions button:disabled {
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
    .questionManagement {
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
    .questionSearch {
        max-width: none;
    }

    .tableFooter .primaryButton {
        width: 100%;
    }

    .modalContent {
        padding: var(--spaceLg);
    }

    .modalActions {
        flex-direction: column-reverse;
    }

    .modalActions button {
        width: 100%;
    }

    .toastMessage {
        top: var(--spaceMd);
        right: var(--spaceMd);
    }
}
</style>
