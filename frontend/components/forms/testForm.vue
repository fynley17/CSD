<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    subjects: {
        type: Array,
        default: () => [],
    },
    cohorts: {
        type: Array,
        default: () => [],
    },
    test: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['save', 'cancel']);

const testName = ref('');
const cohortID = ref('');
const subjectID = ref('');
const testDescription = ref('');
const questionCount = ref(30);
const timeLimit = ref(30);
const dueDate = ref('');
const validationError = ref('');

const filteredSubjects = computed(() => {
    if (!cohortID.value) return [];

    return props.subjects.filter((subject) => String(subject.cohortID) === String(cohortID.value));
});

function populateForm() {
    validationError.value = '';

    if (!props.test) {
        testName.value = '';
        cohortID.value = '';
        subjectID.value = '';
        testDescription.value = '';
        questionCount.value = 30;
        timeLimit.value = 30;
        dueDate.value = '';
        return;
    }

    testName.value = props.test.testName ?? '';
    subjectID.value = props.test.subjectID ?? '';
    testDescription.value = props.test.testDescription ?? '';
    questionCount.value = Number(props.test.questionCount ?? 30);
    timeLimit.value = Number(props.test.timeLimit ?? 30);
    dueDate.value = props.test.dueDate ? String(props.test.dueDate).slice(0, 10) : '';

    const matchingSubject = props.subjects.find((subject) => String(subject.subjectID) === String(subjectID.value));

    cohortID.value = matchingSubject?.cohortID ?? '';
}

watch(() => [props.test, props.subjects], populateForm, { immediate: true });

watch(cohortID, () => {
    if (
        subjectID.value &&
        !filteredSubjects.value.some((subject) => String(subject.subjectID) === String(subjectID.value))
    ) {
        subjectID.value = '';
    }
});

function submitForm() {
    validationError.value = '';

    if (!testName.value.trim()) {
        validationError.value = 'Please enter a test name.';
        return;
    }

    if (!cohortID.value) {
        validationError.value = 'Please select a cohort.';
        return;
    }

    if (!subjectID.value) {
        validationError.value = 'Please select a subject.';
        return;
    }

    if (!questionCount.value || Number(questionCount.value) < 1) {
        validationError.value = 'Please enter a valid question count.';
        return;
    }

    if (!timeLimit.value || Number(timeLimit.value) < 1) {
        validationError.value = 'Please enter a valid time limit.';
        return;
    }

    emit('save', {
        testName: testName.value.trim(),
        subjectID: subjectID.value,
        testDescription: testDescription.value.trim(),
        questionCount: Number(questionCount.value),
        timeLimit: Number(timeLimit.value),
        dueDate: dueDate.value || null,
    });
}
</script>

<template>
    <form class="testForm" @submit.prevent="submitForm">
        <section class="formSection">
            <div class="sectionHeading">
                <h3>Test Details</h3>
                <p>Set up the test and choose which students can access it.</p>
            </div>

            <div class="formGrid">
                <div class="formGroup fullWidth">
                    <label for="testName">Test Name</label>
                    <input id="testName" v-model="testName" type="text" placeholder="Enter test name" maxlength="255" />
                </div>

                <div class="formGroup">
                    <label for="cohort">Cohort</label>
                    <select id="cohort" v-model="cohortID">
                        <option value="" disabled>Select a cohort</option>
                        <option v-for="cohort in cohorts" :key="cohort.cohortID" :value="cohort.cohortID">
                            {{ cohort.cohortName }}
                        </option>
                    </select>
                </div>

                <div class="formGroup">
                    <label for="subject">Subject</label>
                    <select id="subject" v-model="subjectID" :disabled="!cohortID">
                        <option value="" disabled>
                            {{ cohortID ? 'Select a subject' : 'Select a cohort first' }}
                        </option>
                        <option v-for="subject in filteredSubjects" :key="subject.subjectID" :value="subject.subjectID">
                            {{ subject.subjectName }}
                        </option>
                    </select>
                </div>

                <div class="formGroup fullWidth">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        v-model="testDescription"
                        rows="3"
                        placeholder="Enter a description for this test (optional)"
                    ></textarea>
                </div>

                <div class="formGroup fullWidth">
                    <label for="dueDate">Due Date</label>
                    <input id="dueDate" v-model="dueDate" type="date" />
                </div>
            </div>
        </section>

        <section class="formSection settingsSection">
            <div class="sectionHeading">
                <h3>Test Settings</h3>
                <p>Configure the test duration and number of questions.</p>
            </div>

            <div class="formGrid settingsGrid">
                <div class="formGroup">
                    <label for="questionCount">Number of Questions</label>
                    <input id="questionCount" v-model.number="questionCount" type="number" min="1" placeholder="30" />
                    <small> Questions will be selected randomly from the subject question pool. </small>
                </div>

                <div class="formGroup">
                    <label for="timeLimit">Time Limit (minutes)</label>
                    <input id="timeLimit" v-model.number="timeLimit" type="number" min="1" placeholder="30" />
                    <small>Set how long students have to complete the test.</small>
                </div>
            </div>
        </section>

        <p v-if="validationError" class="validationError">
            {{ validationError }}
        </p>

        <div class="formActions">
            <button type="button" class="cancelButton" :disabled="loading" @click="emit('cancel')">Cancel</button>

            <button type="submit" class="saveButton" :disabled="loading">
                {{ loading ? 'Saving...' : test ? 'Save Changes' : 'Create Test' }}
            </button>
        </div>
    </form>
</template>

<style scoped>
.testForm {
    display: flex;
    flex-direction: column;
    gap: var(--spaceXl);
}

.formSection {
    padding-bottom: var(--spaceLg);
    border-bottom: 1px solid var(--colorBorderInput);
}

.settingsSection {
    padding-bottom: 0;
    border-bottom: none;
}

.sectionHeading {
    margin-bottom: var(--spaceMd);
}

.sectionHeading h3 {
    margin: 0 0 var(--spaceXs);
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
    font-weight: 700;
}

.sectionHeading p {
    margin: 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

.formGrid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--spaceMd) var(--spaceLg);
}

.fullWidth {
    grid-column: 1 / -1;
}

.formGroup {
    display: flex;
    flex-direction: column;
    gap: var(--spaceXs);
    min-width: 0;
}

.formGroup label {
    color: var(--colorText);
    font-size: var(--fontSizeSmall);
    font-weight: 600;
}

.formGroup input,
.formGroup select,
.formGroup textarea {
    box-sizing: border-box;
    width: 100%;
    padding: var(--spaceSm);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font: inherit;
}

.formGroup select:disabled {
    background: var(--colorSurfaceAlt);
    cursor: not-allowed;
}

.formGroup textarea {
    resize: vertical;
}

.formGroup input:focus,
.formGroup select:focus,
.formGroup textarea:focus {
    border-color: var(--colorPrimary);
}

.formGroup small {
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
    line-height: 1.4;
}

.validationError {
    margin: 0;
    color: var(--colorError);
    font-size: var(--fontSizeSmall);
}

.formActions {
    display: flex;
    justify-content: flex-end;
    gap: var(--spaceSm);
    padding-top: var(--spaceXs);
}

.formActions button {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.cancelButton {
    background: var(--colorSurface);
    color: var(--colorTextSecondary);
}

.saveButton {
    border-color: var(--colorPrimary);
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

.saveButton:hover:not(:disabled) {
    background: var(--colorPrimaryHover);
}

.formActions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.formGroup input:focus-visible,
.formGroup select:focus-visible,
.formGroup textarea:focus-visible,
.formActions button:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

@media (max-width: 639px) {
    .formGrid {
        grid-template-columns: 1fr;
    }

    .fullWidth {
        grid-column: auto;
    }

    .formActions {
        flex-direction: column-reverse;
    }

    .formActions button {
        width: 100%;
    }
}
</style>
