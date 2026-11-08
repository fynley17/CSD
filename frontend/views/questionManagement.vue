<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref } from 'vue';

import QuestionManagementTable from '../components/tables/questionManagementTable.vue';
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import { nameOptions } from '../utils/filterOptions';
import QuestionForm from '../components/forms/questionForm.vue';
import PopupDialog from '../components/common/popupDialog.vue';

import { getActiveSubjects, createQuestion, updateQuestion, deleteQuestion } from '../services/questionService';

const questionTable = ref(null);
const questionDialog = ref(null);
const deleteDialog = ref(null);

const availableSubjects = ref([]);

const searchText = ref('');
const subjectFilter = ref('');

const showQuestionForm = ref(false);
const selectedQuestion = ref(null);

const saving = ref(false);

const successMessage = ref('');
const errorMessage = ref('');

const questionToDelete = ref(null);
const showDeleteConfirmation = ref(false);

let messageTimeout = null;

const subjects = computed(() => questionTable.value?.subjects ?? []);
const formSubjects = computed(() => availableSubjects.value);

// Load active subjects when the page opens.
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
    }, 10000);
};

// Display a temporary error notification.
const showError = (message) => {
    clearMessages();
    errorMessage.value = message;

    messageTimeout = setTimeout(() => {
        errorMessage.value = '';
        messageTimeout = null;
    }, 10000);
};

// Apply the selected subject filter.
const filterBySubject = () => {
    questionTable.value?.filterBySubject(subjectFilter.value);
};

// Open the form to create a new question.
const openCreateForm = async () => {
    clearMessages();
    selectedQuestion.value = null;
    showQuestionForm.value = true;

    await nextTick();
    questionDialog.value?.open();
};

// Open the form to edit an existing question.
const openEditForm = async (question) => {
    clearMessages();
    selectedQuestion.value = question;
    showQuestionForm.value = true;

    await nextTick();
    questionDialog.value?.open();
};

// Close the create/edit dialog.
const closeQuestionForm = () => {
    questionDialog.value?.close();
};

// Handle the dialog closing, including when Escape is pressed.
const handleQuestionDialogClose = () => {
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

// Open the delete confirmation dialog.
const openDeleteConfirmation = async (question) => {
    clearMessages();
    questionToDelete.value = question;
    showDeleteConfirmation.value = true;

    await nextTick();
    deleteDialog.value?.open();
};

// Close the delete confirmation dialog.
const closeDeleteConfirmation = () => {
    deleteDialog.value?.close();
};

// Handle the delete dialog closing, including Escape.
const handleDeleteDialogClose = () => {
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
    <!-- laid out like lecturer and subject management so every table page matches -->
    <section class="container py-4 adminPage">
        <h1>Question Management</h1>
        <p>Create, edit and manage questions</p>

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

        <!-- search and subject filter on the left, create button on the right -->
        <div class="tableToolbar">
            <div class="tableToolbarControls">
                <TableSearch id="questionSearch" v-model="searchText" placeholder="Question text or subject" />
                <TableFilter
                    id="questionSubjectFilter"
                    v-model="subjectFilter"
                    label="Subject"
                    :options="nameOptions(subjects, 'All subjects')"
                    @change="filterBySubject"
                />
            </div>
            <div class="tableToolbarActions">
                <button type="button" class="btn btn-primary" @click="openCreateForm">
                    <span aria-hidden="true">+</span> Create question
                </button>
            </div>
        </div>

        <QuestionManagementTable
            ref="questionTable"
            :search="searchText"
            @editQuestion="openEditForm"
            @deleteQuestion="openDeleteConfirmation"
        />

        <!-- Create/Edit question dialog. -->
        <PopupDialog
            v-if="showQuestionForm"
            ref="questionDialog"
            :title="selectedQuestion ? 'Edit Question' : 'Create Question'"
            @close="handleQuestionDialogClose"
        >
            <QuestionForm
                :subjects="formSubjects"
                :question="selectedQuestion"
                :loading="saving"
                @save="saveQuestion"
                @cancel="closeQuestionForm"
            />
        </PopupDialog>

        <!-- Delete confirmation dialog, same layout as the lecturer one. -->
        <PopupDialog
            v-if="showDeleteConfirmation"
            ref="deleteDialog"
            title="Confirm Delete"
            @close="handleDeleteDialogClose"
        >
            <div class="text-center">
                <i class="bi bi-exclamation-circle fs-1" aria-hidden="true"></i>
                <p class="fw-bold mb-2">{{ questionToDelete?.question }}</p>
                <p class="mb-0">This question will be deleted and can't be brought back</p>
            </div>

            <template #buttons>
                <button
                    type="button"
                    class="btn btn-outline-primary flex-fill"
                    :disabled="saving"
                    @click="closeDeleteConfirmation"
                >
                    Cancel
                </button>

                <button type="button" class="btn btn-danger flex-fill" :disabled="saving" @click="confirmDelete">
                    {{ saving ? 'Deleting...' : 'Delete' }}
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
