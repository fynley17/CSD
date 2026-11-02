<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import PopupDialog from '../common/popupDialog.vue';
import { createTest, getActiveTestSubjects, updateTest } from '../../services/testService';

const props = defineProps({
    test: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['saved', 'close']);

const dialog = ref(null);
const subjects = ref([]);
const loadingSubjects = ref(false);
const submitting = ref(false);
const errorMessage = ref('');

const isEditing = computed(() => Boolean(props.test?.testID));

const form = ref({
    testName: '',
    subjectID: '',
    description: '',
    timeLimit: '',
    dueDate: '',
    questionCount: '',
});

const dialogTitle = computed(() => (isEditing.value ? 'Edit Test' : 'Create Test'));

const selectedSubject = computed(() => subjects.value.find((subject) => subject.subjectID === form.value.subjectID));

const selectedCohortName = computed(() => selectedSubject.value?.cohortName ?? '');

function populateForm() {
    if (!props.test) {
        return;
    }

    form.value = {
        testName: props.test.testName ?? '',
        subjectID: props.test.subjectID ?? '',
        description: props.test.description ?? props.test.testDescription ?? '',
        timeLimit: props.test.timeLimit ?? '',
        dueDate: props.test.dueDate ? String(props.test.dueDate).slice(0, 10) : '',
        questionCount: props.test.questionCount ?? '',
    };
}

async function loadSubjects() {
    loadingSubjects.value = true;
    errorMessage.value = '';

    try {
        const response = await getActiveTestSubjects();

        subjects.value = Array.isArray(response) ? response : (response?.subjects ?? response?.data ?? []);
    } catch {
        errorMessage.value = 'Unable to load subjects. Please try again.';
    } finally {
        loadingSubjects.value = false;
    }
}

function validateForm() {
    const name = form.value.testName.trim();
    const description = form.value.description.trim();
    const questionCount = Number(form.value.questionCount);

    const timeLimit = form.value.timeLimit === '' ? null : Number(form.value.timeLimit);

    if (!name) {
        errorMessage.value = 'Please enter a test name.';
        return false;
    }

    if (name.length > 30) {
        errorMessage.value = 'The test name cannot exceed 30 characters.';
        return false;
    }

    if (!form.value.subjectID) {
        errorMessage.value = 'Please select a subject.';
        return false;
    }

    if (description.length > 100) {
        errorMessage.value = 'The description cannot exceed 100 characters.';
        return false;
    }

    if (!Number.isInteger(questionCount) || questionCount < 1) {
        errorMessage.value = 'Question count must be at least 1.';
        return false;
    }

    if (timeLimit !== null && (!Number.isInteger(timeLimit) || timeLimit < 1)) {
        errorMessage.value = 'Time limit must be a whole number of at least 1 minute.';
        return false;
    }

    return true;
}

async function submitTest() {
    errorMessage.value = '';

    if (!validateForm()) {
        return;
    }

    const testData = {
        testName: form.value.testName.trim(),
        subjectID: form.value.subjectID,
        testDescription: form.value.description.trim(),
        timeLimit: form.value.timeLimit === '' ? null : Number(form.value.timeLimit),
        dueDate: form.value.dueDate || null,
        questionCount: Number(form.value.questionCount),
    };

    submitting.value = true;

    try {
        if (isEditing.value) {
            await updateTest(props.test.testID, testData);
        } else {
            await createTest(testData);
        }

        emit('saved');
        dialog.value?.close();
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ?? 'Unable to save the test. Please check your details and try again.';
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    populateForm();
    await loadSubjects();
    await nextTick();
    dialog.value?.open();
});
</script>

<template>
    <PopupDialog ref="dialog" :title="dialogTitle" @close="emit('close')">
        <form id="test-form" class="testForm" @submit.prevent="submitTest">
            <div v-if="errorMessage" class="formError" role="alert">
                {{ errorMessage }}
            </div>

            <div class="formGroup">
                <label for="testName"> Test name <span aria-hidden="true">*</span> </label>
                <input
                    id="testName"
                    v-model="form.testName"
                    type="text"
                    maxlength="30"
                    required
                    placeholder="Enter test name"
                />
                <small>{{ form.testName.length }}/30 characters</small>
            </div>

            <div class="formGroup">
                <label for="subjectID"> Subject <span aria-hidden="true">*</span> </label>
                <select id="subjectID" v-model="form.subjectID" required :disabled="loadingSubjects">
                    <option value="" disabled>
                        {{ loadingSubjects ? 'Loading subjects...' : 'Select a subject' }}
                    </option>
                    <option v-for="subject in subjects" :key="subject.subjectID" :value="subject.subjectID">
                        {{ subject.subjectName }}
                    </option>
                </select>
            </div>

            <div v-if="form.subjectID && selectedSubject" class="formGroup cohortDisplay">
                <label for="cohortName">Cohort</label>
                <input id="cohortName" :value="selectedCohortName" type="text" readonly aria-readonly="true" />
                <small>The cohort is determined automatically by the selected subject.</small>
            </div>

            <div class="formGroup">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    v-model="form.description"
                    maxlength="100"
                    rows="3"
                    placeholder="Enter a short description"
                />
                <small>{{ form.description.length }}/100 characters</small>
            </div>

            <div class="formRow">
                <div class="formGroup">
                    <label for="questionCount"> Questions per attempt <span aria-hidden="true">*</span> </label>
                    <input id="questionCount" v-model="form.questionCount" type="number" min="1" step="1" required />
                </div>

                <div class="formGroup">
                    <label for="timeLimit">Time limit (minutes)</label>
                    <input
                        id="timeLimit"
                        v-model="form.timeLimit"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="No limit"
                    />
                </div>
            </div>

            <div class="formGroup">
                <label for="dueDate">Due date</label>
                <input id="dueDate" v-model="form.dueDate" type="date" />
            </div>
        </form>

        <template #buttons>
            <button type="button" class="secondaryButton" :disabled="submitting" @click="dialog?.close()">
                Cancel
            </button>

            <button type="submit" form="test-form" class="primaryButton" :disabled="submitting || loadingSubjects">
                {{ submitting ? 'Saving...' : isEditing ? 'Save Changes' : 'Create Test' }}
            </button>
        </template>
    </PopupDialog>
</template>

<style scoped>
.testForm {
    display: flex;
    flex-direction: column;
    gap: var(--spaceMd);
    min-width: 0;
}

.formGroup {
    display: flex;
    flex-direction: column;
    gap: var(--spaceXs);
    flex: 1;
}

.formGroup label {
    font-weight: 600;
    color: var(--colorText);
}

.formGroup input,
.formGroup select,
.formGroup textarea {
    width: 100%;
    padding: var(--spaceSm);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    font: inherit;
    background-color: var(--colorSurface);
    color: var(--colorText);
}

.formGroup input:focus,
.formGroup select:focus,
.formGroup textarea:focus {
    outline: 2px solid var(--colorFocus);
    outline-offset: 2px;
}

.formGroup small {
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

.cohortDisplay input {
    background-color: var(--colorSurfaceAlt);
    cursor: default;
}

.formRow {
    display: flex;
    gap: var(--spaceMd);
}

.formError {
    padding: var(--spaceSm);
    border: 1px solid var(--colorError);
    border-radius: var(--radiusSm);
    background-color: var(--colorErrorLight);
    color: var(--colorError);
}

.primaryButton,
.secondaryButton {
    padding: var(--spaceButtonVertical) var(--spaceButtonHorizontal);
    border: 1px solid transparent;
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.primaryButton {
    background-color: var(--colorPrimary);
    color: var(--colorSurface);
}

.secondaryButton {
    background-color: var(--colorSurfaceAlt);
    color: var(--colorText);
}

.primaryButton:disabled,
.secondaryButton:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.primaryButton:focus-visible,
.secondaryButton:focus-visible {
    outline: 2px solid var(--colorFocus);
    outline-offset: 2px;
}

@media (max-width: 480px) {
    .formRow {
        flex-direction: column;
    }
}
</style>
