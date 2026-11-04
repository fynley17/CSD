<script setup>
import { computed, onMounted, nextTick, ref } from 'vue';
import PopupDialog from '../common/popupDialog.vue';
import api from '../../services/api';
import { createStudent, updateStudent } from '../../services/studentService';

const props = defineProps({
    student: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['saved', 'close']);

const isEditing = computed(() => Boolean(props.student?.userID));

const dialog = ref(null);
const cohorts = ref([]);
const loadingCohorts = ref(false);
const submitting = ref(false);
const errorMessage = ref('');

// Populate the form with the selected student's details when editing.
const form = ref({
    firstName: props.student?.firstName ?? '',
    lastName: props.student?.lastName ?? '',
    studentNumber: props.student?.studentNumber ?? '',
    email: props.student?.email ?? '',
    cohortID: props.student?.cohortID ?? '',
});

// Load the available cohorts for the lecturer to assign the student to.
async function loadCohorts() {
    loadingCohorts.value = true;
    errorMessage.value = '';

    try {
        const response = await api.get('/cohorts');
        const availableCohorts = Array.isArray(response.data) ? response.data : (response.data?.cohorts ?? []);

        // Keep the student's current cohort available when editing, even if it is inactive.
        const currentCohortID = String(props.student?.cohortID ?? '');
        const currentCohortIsAvailable = availableCohorts.some((cohort) => String(cohort.cohortID) === currentCohortID);

        if (isEditing.value && currentCohortID && !currentCohortIsAvailable) {
            availableCohorts.push({
                cohortID: props.student.cohortID,
                cohortName: props.student.cohort ? `${props.student.cohort} (inactive)` : 'Current cohort (inactive)',
            });
        }

        cohorts.value = availableCohorts;
    } catch {
        errorMessage.value = 'Unable to load cohorts. Please try again.';
    } finally {
        loadingCohorts.value = false;
    }
}

// Validate the details before sending them to the backend.
function validateForm() {
    if (!form.value.firstName.trim()) {
        errorMessage.value = "Please enter the student's first name.";
        return false;
    }

    if (!form.value.lastName.trim()) {
        errorMessage.value = "Please enter the student's last name.";
        return false;
    }

    if (!/^ws\d{6}$/i.test(form.value.studentNumber.trim())) {
        errorMessage.value = 'Please enter a valid student number, for example ws123456.';
        return false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email.trim())) {
        errorMessage.value = 'Please enter a valid email address.';
        return false;
    }

    if (!form.value.cohortID) {
        errorMessage.value = 'Please select a cohort.';
        return false;
    }

    return true;
}

// Create a new student or update the selected student's details.
async function submitStudent() {
    errorMessage.value = '';

    if (!validateForm()) {
        return;
    }

    submitting.value = true;

    const studentData = {
        firstName: form.value.firstName.trim(),
        lastName: form.value.lastName.trim(),
        studentNumber: form.value.studentNumber.trim().toLowerCase(),
        email: form.value.email.trim().toLowerCase(),
        cohortID: form.value.cohortID,
    };

    try {
        let response;

        if (isEditing.value) {
            response = await updateStudent(props.student.userID, studentData);
        } else {
            response = await createStudent(studentData);
        }

        emit('saved', response);
        dialog.value?.close();
    } catch (error) {
        const action = isEditing.value ? 'update' : 'create';

        errorMessage.value =
            error.response?.data?.error ??
            error.response?.data?.message ??
            `Unable to ${action} the student account. Please try again.`;
    } finally {
        submitting.value = false;
    }
}

// Load cohorts and open the dialog when the form is mounted.
onMounted(async () => {
    await loadCohorts();
    await nextTick();
    dialog.value?.open();
});
</script>

<template>
    <PopupDialog ref="dialog" :title="isEditing ? 'Edit Student' : 'Add New Student'" @close="emit('close')">
        <form id="student-form" class="studentForm" @submit.prevent="submitStudent">
            <div v-if="errorMessage" class="formError" role="alert">
                {{ errorMessage }}
            </div>

            <div class="formGroup">
                <label for="firstName">First name <span aria-hidden="true">*</span></label>
                <input
                    id="firstName"
                    v-model="form.firstName"
                    type="text"
                    maxlength="100"
                    autocomplete="given-name"
                    required
                    placeholder="Enter first name"
                    :disabled="submitting"
                />
            </div>

            <div class="formGroup">
                <label for="lastName">Last name <span aria-hidden="true">*</span></label>
                <input
                    id="lastName"
                    v-model="form.lastName"
                    type="text"
                    maxlength="100"
                    autocomplete="family-name"
                    required
                    placeholder="Enter last name"
                    :disabled="submitting"
                />
            </div>

            <div class="formGroup">
                <label for="studentNumber">Student number <span aria-hidden="true">*</span></label>
                <input
                    id="studentNumber"
                    v-model="form.studentNumber"
                    type="text"
                    maxlength="8"
                    required
                    placeholder="e.g. ws123456"
                    :disabled="submitting"
                />
                <small>Enter the student's eight-character student number.</small>
            </div>

            <div class="formGroup">
                <label for="studentEmail">Email address <span aria-hidden="true">*</span></label>
                <input
                    id="studentEmail"
                    v-model="form.email"
                    type="email"
                    maxlength="255"
                    autocomplete="email"
                    required
                    placeholder="Enter email address"
                    :disabled="submitting"
                />
                <small>The student's email address is used for their account.</small>
            </div>

            <div class="formGroup">
                <label for="cohortID">Cohort <span aria-hidden="true">*</span></label>
                <select
                    id="cohortID"
                    v-model="form.cohortID"
                    required
                    :disabled="isEditing || loadingCohorts || submitting || cohorts.length === 0"
                >
                    <option value="" disabled>
                        {{ loadingCohorts ? 'Loading cohorts...' : 'Select a cohort' }}
                    </option>

                    <option v-for="cohort in cohorts" :key="cohort.cohortID" :value="cohort.cohortID">
                        {{ cohort.cohortName }}
                    </option>
                </select>

                <small v-if="!loadingCohorts && cohorts.length === 0"> No cohorts are currently available. </small>
            </div>
        </form>

        <template #buttons>
            <div class="studentFormActions">
                <button type="button" class="studentCancelButton" :disabled="submitting" @click="dialog?.close()">
                    Cancel
                </button>

                <button
                    type="submit"
                    form="student-form"
                    class="studentCreateButton"
                    :disabled="submitting || loadingCohorts || cohorts.length === 0"
                >
                    {{
                        submitting
                            ? isEditing
                                ? 'Saving...'
                                : 'Creating...'
                            : isEditing
                              ? 'Save Changes'
                              : 'Create Student'
                    }}
                </button>
            </div>
        </template>
    </PopupDialog>
</template>

<style scoped>
.studentForm {
    display: flex;
    flex-direction: column;
    gap: var(--spaceMd);
}

.studentForm .formGroup {
    display: flex;
    flex-direction: column;
    gap: var(--spaceXs);
}

.studentForm label {
    font-weight: 600;
}

.studentForm input,
.studentForm select {
    width: 100%;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font: inherit;
}

.studentForm input:focus,
.studentForm select:focus {
    outline: 3px solid var(--colorFocus);
    outline-offset: 1px;
}

.studentForm small {
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

.studentForm .formError {
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorError);
    border-radius: var(--radiusSm);
    color: var(--colorError);
    background: var(--colorErrorLight);
}

/* Align the form actions neatly at the bottom of the dialog. */
.studentFormActions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: var(--spaceSm);
    width: 100%;
}

/* Shared button styling. */
.studentFormActions button {
    min-height: 42px;
    padding: var(--spaceSm) var(--spaceLg);
    border: 1px solid transparent;
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease;
}

/* Secondary cancel button. */
.studentCancelButton {
    color: var(--colorText);
    background: var(--colorSurface);
    border-color: var(--colorBorderInput);
}

.studentCancelButton:hover:not(:disabled) {
    color: var(--colorPrimary);
    border-color: var(--colorPrimary);
}

/* Primary create or save button. */
.studentCreateButton {
    color: var(--colorSurface);
    background: var(--colorPrimary);
    border-color: var(--colorPrimary);
}

.studentCreateButton:hover:not(:disabled) {
    background: var(--colorPrimaryHover);
    border-color: var(--colorPrimaryHover);
}

/* Disabled button appearance. */
.studentFormActions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Accessible keyboard focus. */
.studentFormActions button:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 3px;
}

/* Respect users who prefer reduced motion. */
@media (prefers-reduced-motion: reduce) {
    .studentFormActions button {
        transition: none;
    }
}
</style>
