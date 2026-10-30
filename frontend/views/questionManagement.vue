<script setup>
// Import Vue functions for reactive state and lifecycle management.
import { computed, onMounted, ref } from 'vue';

// Import the existing question table and reusable question form.
import QuestionManagementTable from '../components/tables/questionManagementTable.vue';
import QuestionForm from '../components/forms/questionForm.vue';

// Import the services used to manage questions and subjects.
import { getActiveSubjects, createQuestion, updateQuestion, deleteQuestion } from '../services/questionService';

// Store the table reference so the page can apply filters and searches.
const questionTable = ref(null);

// Store the available subjects returned by the backend.
const availableSubjects = ref([]);

// Track the selected subject and the text entered into the search box.
const subjectFilter = ref('');
const searchTerm = ref('');

// Track whether the create/edit form is currently visible.
const showQuestionForm = ref(false);

// Store the question currently being edited.
// A null value means the form is being used to create a question.
const selectedQuestion = ref(null);

// Track whether a save operation is in progress.
const saving = ref(false);

// Store success and error feedback messages.
const successMessage = ref('');
const errorMessage = ref('');

// Store the question awaiting deletion confirmation.
const questionToDelete = ref(null);

// Track whether the delete confirmation modal is visible.
const showDeleteConfirmation = ref(false);

// Retrieve the subject names from the question table for its filter.
const subjects = computed(() => questionTable.value?.subjects ?? []);

// Retrieve the active subjects for the question form.
const formSubjects = computed(() => availableSubjects.value);

// Load the active subjects when the page is opened.
onMounted(async () => {
    try {
        availableSubjects.value = await getActiveSubjects();
    } catch (error) {
        errorMessage.value = error.response?.data?.error ?? 'Unable to load subjects. Please try again.';
    }
});

// Clear existing feedback messages.
const clearMessages = () => {
    successMessage.value = '';
    errorMessage.value = '';
};

// Apply the selected subject filter to the question table.
const filterBySubject = () => {
    questionTable.value?.filterBySubject(subjectFilter.value);
};

// Search the questions using the DataTables search API.
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
            // Update the existing question.
            await updateQuestion(selectedQuestion.value.questionID, questionData);

            successMessage.value = 'Question updated successfully.';
        } else {
            // Create a new question.
            await createQuestion(questionData);

            successMessage.value = 'Question created successfully.';
        }

        // Close the form and refresh the displayed questions.
        closeQuestionForm();
        await refreshQuestions();
    } catch (error) {
        // Display the backend validation message where available.
        errorMessage.value = error.response?.data?.error ?? 'Unable to save the question. Please try again.';
    } finally {
        saving.value = false;
    }
};

// Open the delete confirmation modal for the selected question.
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

        successMessage.value = 'Question deleted successfully.';

        // Close the confirmation and refresh the table.
        closeDeleteConfirmation();
        await refreshQuestions();
    } catch (error) {
        // This also displays the backend message if historical answers
        // prevent the question from being deleted.
        errorMessage.value = error.response?.data?.error ?? 'Unable to delete the question. Please try again.';
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <main class="questionManagement">
        <!-- Page heading and short description. -->
        <div class="pageHeading">
            <div>
                <h1>Question Management</h1>
                <p>Create, edit and manage questions</p>
            </div>
        </div>

        <!-- Display success feedback after a successful operation. -->
        <div v-if="successMessage" class="successMessage" role="status">
            {{ successMessage }}
        </div>

        <!-- Display errors returned by the backend or frontend. -->
        <div v-if="errorMessage" class="errorMessage" role="alert">
            {{ errorMessage }}
        </div>

        <!-- Toolbar containing the subject filter and search input. -->
        <div class="tableToolbar">
            <label class="subjectFilter">
                <span class="visuallyHidden">Filter questions by subject</span>

                <!-- Populate the dropdown from the subjects returned by the table. -->
                <select v-model="subjectFilter" @change="filterBySubject">
                    <option value="">All subjects</option>

                    <option v-for="subject in subjects" :key="subject" :value="subject">
                        {{ subject }}
                    </option>
                </select>
            </label>

            <label class="questionSearch">
                <span class="visuallyHidden">Search questions</span>

                <!-- Search the table as the user types. -->
                <input v-model="searchTerm" type="search" placeholder="Search questions..." @input="searchQuestions" />
            </label>
        </div>

        <!-- Display the questions table, including its pagination controls. -->
        <div class="tableSection">
            <QuestionManagementTable
                ref="questionTable"
                @editQuestion="openEditForm"
                @deleteQuestion="openDeleteConfirmation"
            />

            <!-- Align the create button below the table, on the right. -->
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

                <!-- Display the actual question being deleted. -->
                <div class="questionToDelete">
                    {{ questionToDelete?.question }}
                </div>

                <!-- Delete confirmation actions. -->
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
/* Centre the page and apply the shared application typography and spacing. */
.questionManagement {
    width: 100%;
    max-width: var(--contentMaxWidth);
    min-height: 100%;
    margin: 0 auto;
    padding: var(--space2xl) var(--spaceLg);
    color: var(--colorText);
    font-family: var(--fontBody);
}

/* Position the page title and description consistently with other management pages. */
.pageHeading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceMd);
    margin-bottom: var(--space2xl);
}

/* Apply the shared heading font and brand colour. */
.pageHeading h1 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

/* Style the supporting description beneath the page heading. */
.pageHeading p {
    margin: var(--spaceXs) 0 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

/* Place the subject filter and search input on the same row. */
.tableToolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceLg);
    margin-bottom: var(--spaceMd);
}

/* Allow both toolbar controls to shrink within the available space. */
.subjectFilter,
.questionSearch {
    flex: 1;
    min-width: 0;
}

/* Keep the subject dropdown wider than the search input. */
.subjectFilter {
    max-width: 405px;
}

/* Align the search box to the right and limit its width. */
.questionSearch {
    max-width: 320px;
    margin-left: auto;
}

/* Use consistent sizing, borders and typography for both toolbar controls. */
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

/* Keep the table section within the available page width. */
.tableSection {
    width: 100%;
    min-width: 0;
}

/* Align the create button beneath the table to the right. */
.tableFooter {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--spaceMd);
}

/* Apply the shared primary button styling. */
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

/* Provide visual feedback when the primary button is hovered over. */
.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

/* Display success feedback. */
.successMessage {
    margin-bottom: var(--spaceMd);
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    color: var(--colorText);
    background: var(--colorSurface);
}

/* Display error feedback. */
.errorMessage {
    margin-bottom: var(--spaceMd);
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorError);
    border-radius: var(--radiusSm);
    color: var(--colorError);
    background: var(--colorSurface);
}

/* Cover the page behind an open modal. */
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

/* Main modal panel. */
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

/* Delete confirmation modal heading. */
.deleteModal h2 {
    margin-top: 0;
    font-family: var(--fontHeading);
    color: var(--colorText);
}

/* Delete confirmation description. */
.deleteModal > p {
    color: var(--colorTextSecondary);
    line-height: 1.5;
}

/* Highlight the question that is about to be deleted. */
.questionToDelete {
    margin: var(--spaceMd) 0;
    padding: var(--spaceMd);
    border: 1px solid var(--colorBorder);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    font-weight: 600;
}

/* Align the delete modal action buttons. */
.modalActions {
    display: flex;
    justify-content: flex-end;
    gap: var(--spaceSm);
    margin-top: var(--spaceLg);
}

/* Shared modal button styling. */
.modalActions button {
    min-height: 40px;
    padding: var(--spaceSm) var(--spaceMd);
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

/* Cancel button styling. */
.cancelButton {
    border: 1px solid var(--colorBorder);
    background: var(--colorSurface);
    color: var(--colorText);
}

/* Confirm delete button styling. */
.deleteConfirmButton {
    border: 1px solid var(--colorError);
    background: var(--colorError);
    color: var(--colorSurface);
}

/* Make keyboard focus visible on interactive controls. */
button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

/* Disabled modal button styling. */
.modalActions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Hide labels visually while keeping them available to assistive technology. */
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

/* Stack the toolbar controls on smaller screens for easier use. */
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

    /* Allow both controls to use the full available width on mobile. */
    .subjectFilter,
    .questionSearch {
        max-width: none;
    }

    /* Make the create button easier to tap on smaller screens. */
    .tableFooter .primaryButton {
        width: 100%;
    }

    /* Use the available screen width for the modal on mobile. */
    .modalContent {
        padding: var(--spaceLg);
    }

    .modalActions {
        flex-direction: column-reverse;
    }

    .modalActions button {
        width: 100%;
    }
}
</style>
