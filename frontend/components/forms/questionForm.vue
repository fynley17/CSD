<script setup>
// Import Vue functions for reactive form data and watching prop changes.
import { ref, watch } from 'vue';

// Define the data passed into this reusable form component.
const props = defineProps({
    // Available subjects, including their IDs and names.
    subjects: {
        type: Array,
        default: () => [],
    },

    // Existing question data when editing.
    // Null means the form is being used to create a new question.
    question: {
        type: Object,
        default: null,
    },

    // Disable the form while the parent is saving.
    loading: {
        type: Boolean,
        default: false,
    },
});

// Define events sent back to the parent component.
const emit = defineEmits(['save', 'cancel']);

// Store the selected subject ID.
const subjectID = ref('');

// Store the question text.
const questionText = ref('');

// Store the four answer options.
const answers = ref([
    { answerText: '', isCorrect: false },
    { answerText: '', isCorrect: false },
    { answerText: '', isCorrect: false },
    { answerText: '', isCorrect: false },
]);

// Store validation feedback.
const errorMessage = ref('');

// Determine whether the form is creating or editing.
const isEditing = ref(false);

// Populate the form when an existing question is supplied.
// Reset it when the form is opened for a new question.
const populateForm = () => {
    errorMessage.value = '';

    if (props.question) {
        isEditing.value = true;

        // Populate the existing question details.
        subjectID.value = props.question.subjectID ?? '';
        questionText.value = props.question.question ?? '';

        // Copy the existing answers into the form.
        // Keep the answer IDs available so the backend can preserve
        // historical student answer references when editing.
        if (Array.isArray(props.question.answers)) {
            answers.value = Array.from({ length: 4 }, (_, index) => {
                const existingAnswer = props.question.answers[index];

                return {
                    answerID: existingAnswer?.answerID ?? null,
                    answerText: existingAnswer?.answerText ?? '',
                    isCorrect: Boolean(existingAnswer?.isCorrect ?? existingAnswer?.is_correct ?? false),
                };
            });
        } else {
            answers.value = createEmptyAnswers();
        }
    } else {
        isEditing.value = false;

        // Clear the form for a new question.
        subjectID.value = '';
        questionText.value = '';
        answers.value = createEmptyAnswers();
    }
};

// Create four blank answer options.
const createEmptyAnswers = () => [
    { answerText: '', isCorrect: false },
    { answerText: '', isCorrect: false },
    { answerText: '', isCorrect: false },
    { answerText: '', isCorrect: false },
];

// Watch for changes to the question prop.
// This allows the same component to switch between Create and Edit.
watch(() => props.question, populateForm, { immediate: true });

// Select one answer as the correct answer.
// Selecting another option automatically deselects the previous one.
const selectCorrectAnswer = (selectedIndex) => {
    answers.value = answers.value.map((answer, index) => ({
        ...answer,
        isCorrect: index === selectedIndex,
    }));
};

// Validate and submit the form.
const submitForm = () => {
    errorMessage.value = '';

    // Ensure a subject has been selected.
    if (!subjectID.value) {
        errorMessage.value = 'Please select a subject.';
        return;
    }

    // Ensure the question contains text.
    if (!questionText.value.trim()) {
        errorMessage.value = 'Please enter a question.';
        return;
    }

    // Ensure exactly four answers have been provided.
    if (answers.value.length !== 4 || answers.value.some((answer) => !answer.answerText.trim())) {
        errorMessage.value = 'Please complete all four answer options.';
        return;
    }

    // Ensure exactly one answer is marked as correct.
    const correctAnswers = answers.value.filter((answer) => answer.isCorrect).length;

    if (correctAnswers !== 1) {
        errorMessage.value = 'Please select exactly one correct answer.';
        return;
    }

    // Prepare the question data for the parent component.
    const questionData = {
        subjectID: subjectID.value,
        question: questionText.value.trim(),
        answers: answers.value.map((answer) => ({
            ...(answer.answerID ? { answerID: answer.answerID } : {}),
            answerText: answer.answerText.trim(),
            isCorrect: answer.isCorrect,
        })),
    };

    // Emit the completed form data to the parent.
    emit('save', questionData);
};

// Tell the parent when the user cancels the form.
const cancelForm = () => {
    emit('cancel');
};
</script>

<template>
    <div class="questionForm">
        <!-- Display the form heading depending on the current mode. -->
        <div class="formHeading">
            <h2>{{ isEditing ? 'Edit Question' : 'Create New Question' }}</h2>
            <p>
                {{
                    isEditing
                        ? 'Update the question and its answer options.'
                        : 'Complete the details below to create a new question.'
                }}
            </p>
        </div>

        <!-- Display validation errors accessibly. -->
        <div v-if="errorMessage" class="formError" role="alert">
            {{ errorMessage }}
        </div>

        <!-- Question creation and editing form. -->
        <form @submit.prevent="submitForm">
            <!-- Subject selection. -->
            <div class="formGroup">
                <label for="questionSubject">Subject</label>

                <select id="questionSubject" v-model="subjectID" required :disabled="loading">
                    <option value="" disabled>Select a subject</option>

                    <option v-for="subject in subjects" :key="subject.subjectID" :value="subject.subjectID">
                        {{ subject.subjectName }}
                    </option>
                </select>
            </div>

            <!-- Main question text. -->
            <div class="formGroup">
                <label for="questionText">Question</label>

                <textarea
                    id="questionText"
                    v-model="questionText"
                    placeholder="Enter your question..."
                    rows="4"
                    maxlength="1000"
                    required
                    :disabled="loading"
                ></textarea>
            </div>

            <!-- Answer options and correct answer selection. -->
            <div class="answersSection">
                <div class="answersHeading">
                    <h3>Answer Options</h3>
                    <p>Select the radio button beside the correct answer.</p>
                </div>

                <!-- Display exactly four answer fields. -->
                <div v-for="(answer, index) in answers" :key="index" class="answerGroup">
                    <label :for="`answer-${index}`"> Answer {{ index + 1 }} </label>

                    <div class="answerInputGroup">
                        <!-- Answer text input. -->
                        <input
                            :id="`answer-${index}`"
                            v-model="answer.answerText"
                            type="text"
                            :placeholder="`Enter answer ${index + 1}`"
                            maxlength="255"
                            required
                            :disabled="loading"
                        />

                        <!-- Radio button for selecting the correct answer. -->
                        <label :for="`correct-${index}`" class="correctAnswerLabel">
                            <input
                                :id="`correct-${index}`"
                                type="radio"
                                name="correctAnswer"
                                :checked="answer.isCorrect"
                                :disabled="loading"
                                @change="selectCorrectAnswer(index)"
                            />
                            <span>Correct</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form action buttons. -->
            <div class="formActions">
                <button type="button" class="cancelButton" :disabled="loading" @click="cancelForm">Cancel</button>

                <button type="submit" class="submitButton" :disabled="loading">
                    {{ loading ? 'Saving...' : isEditing ? 'Save Changes' : 'Create Question' }}
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
/* Main form container. */
.questionForm {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: var(--spaceMd);
}

/* Form heading and introductory text. */
.formHeading h2 {
    margin: 0 0 var(--spaceXs);
    font-family: var(--fontHeading);
    color: var(--colorText);
}

.formHeading p,
.answersHeading p {
    margin: 0;
    color: var(--colorTextMuted);
    font-family: var(--fontBody);
}

/* Shared spacing for form sections. */
.formGroup,
.answerGroup {
    display: flex;
    flex-direction: column;
    gap: var(--spaceXs);
    margin-bottom: var(--spaceMd);
}

/* Form labels. */
.formGroup > label,
.answerGroup > label {
    font-weight: 600;
    color: var(--colorText);
    font-family: var(--fontBody);
}

/* Text inputs, subject dropdown and textarea. */
.questionForm select,
.questionForm textarea,
.questionForm input[type='text'] {
    width: 100%;
    min-height: 42px;
    padding: var(--spaceSm);
    border: 1px solid var(--colorBorder);
    border-radius: var(--radiusSm);
    background-color: var(--colorSurface);
    color: var(--colorText);
    font-family: var(--fontBody);
    box-sizing: border-box;
}

/* Allow the question textarea to be resized vertically. */
.questionForm textarea {
    resize: vertical;
}

/* Keep the answer input flexible. */
.answerInputGroup > input[type='text'] {
    flex: 1;
    min-width: 0;
}

/* Highlight focused form fields. */
.questionForm select:focus,
.questionForm textarea:focus,
.questionForm input[type='text']:focus {
    outline: 2px solid var(--colorFocus);
    outline-offset: 2px;
}

/* Answer section heading. */
.answersHeading {
    margin-bottom: var(--spaceMd);
}

.answersHeading h3 {
    margin: 0 0 var(--spaceXs);
    color: var(--colorText);
    font-family: var(--fontHeading);
}

/* Arrange each answer input beside its correct-answer radio button. */
.answerInputGroup {
    display: flex;
    align-items: center;
    gap: var(--spaceMd);
}

/* Correct answer radio button and label. */
.correctAnswerLabel {
    display: flex;
    align-items: center;
    gap: var(--spaceXs);
    white-space: nowrap;
    color: var(--colorText);
    font-family: var(--fontBody);
    cursor: pointer;
}

/* Use the primary theme colour for the selected radio button. */
.correctAnswerLabel input {
    accent-color: var(--colorPrimary);
}

/* Validation error message. */
.formError {
    padding: var(--spaceSm);
    border: 1px solid var(--colorError);
    border-radius: var(--radiusSm);
    color: var(--colorError);
    background-color: var(--colorSurface);
}

/* Align the Cancel and Save buttons. */
.formActions {
    display: flex;
    justify-content: flex-end;
    gap: var(--spaceSm);
    margin-top: var(--spaceMd);
}

/* Shared button styling. */
.formActions button {
    min-height: 40px;
    padding: var(--spaceSm) var(--spaceMd);
    border-radius: var(--radiusSm);
    font-family: var(--fontBody);
    font-weight: 600;
    cursor: pointer;
}

/* Cancel button. */
.cancelButton {
    color: var(--colorText);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorBorder);
}

/* Primary submit button. */
.submitButton {
    color: var(--colorSurface);
    background-color: var(--colorPrimary);
    border: 1px solid var(--colorPrimary);
}

/* Hover styling for the submit button. */
.submitButton:hover:not(:disabled) {
    background-color: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

/* Disabled button state. */
.formActions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Make the form usable on smaller screens. */
@media (max-width: 600px) {
    .answerInputGroup {
        align-items: stretch;
        flex-direction: column;
    }

    .formActions {
        flex-direction: column-reverse;
    }

    .formActions button {
        width: 100%;
    }
}
</style>
