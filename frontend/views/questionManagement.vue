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
const errorToast = ref(null);

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
    errorToast.value?.hidePopover();
    errorMessage.value = '';
    successMessage.value = message;

    messageTimeout = setTimeout(() => {
        successMessage.value = '';
        messageTimeout = null;
    }, 20000);
};

// Display a temporary error notification.
const showError = async (message) => {
    clearMessages();
    errorMessage.value = message;

    await nextTick();
    errorToast.value?.showPopover();

    messageTimeout = setTimeout(() => {
        errorToast.value?.hidePopover();
        errorMessage.value = '';
        messageTimeout = null;
    }, 20000);
};

const closeSuccess = () => {
    clearTimeout(messageTimeout);
    messageTimeout = null;
    successMessage.value = '';
};

const closeError = () => {
    clearTimeout(messageTimeout);
    messageTimeout = null;
    errorToast.value?.hidePopover();
    errorMessage.value = '';
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
const closeQuestionForm = async () => {
    questionDialog.value?.close();
    await nextTick();
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

            await closeQuestionForm();
            await refreshQuestions();

            showSuccess('Question updated successfully.');
        } else {
            await createQuestion(questionData);

            await closeQuestionForm();
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
const closeDeleteConfirmation = async () => {
    deleteDialog.value?.close();
    await nextTick();
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

        await closeDeleteConfirmation();
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
            <span>{{ successMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeSuccess">×</button>
        </div>

        <!-- Temporary error notification. -->
        <div
            v-if="errorMessage"
            ref="errorToast"
            popover="manual"
            class="toastMessage errorMessage"
            role="alert"
            aria-live="assertive"
        >
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ errorMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeError">×</button>
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
                @error="showError"
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
