<!-- Pop-up for taking a student out of a cohort, every student needs a cohort so it asks where they move to -->
<template>
    <PopupDialog ref="dialog" title="Remove from Cohort" @close="emit('close')">
        <form id="moveStudentForm" novalidate @submit.prevent="move">
            <h3 class="cohortDialogStudent">{{ student.name }} ({{ student.studentNumber }})</h3>
            <p class="cohortDialogNote">Every student must belong to a cohort, so choose where they move to.</p>

            <template v-if="otherCohorts.length > 0">
                <label class="cohortDialogLabel mt-3" for="newCohort">New cohort</label>
                <select
                    id="newCohort"
                    v-model="newCohortID"
                    class="cohortDialogSearch"
                    required
                    :aria-invalid="isCohortInvalid ? 'true' : null"
                    :aria-describedby="isCohortInvalid ? 'moveStudentError' : null"
                >
                    <option value="" disabled>Select a cohort</option>
                    <option v-for="other in otherCohorts" :key="other.cohortID" :value="other.cohortID">
                        {{ other.cohortName }}{{ other.isActive ? '' : ' (disabled)' }}
                    </option>
                </select>
                <p v-if="newCohortIsDisabled" class="cohortDialogNote">
                    This cohort is disabled, so this student won't be able to log in.
                </p>
            </template>

            <p v-else class="mt-3 mb-0">There are no other cohorts to move this student to. Create one first.</p>
        </form>

        <div v-if="errorMessage" id="moveStudentError" class="alert alert-danger mt-3 mb-0" role="alert">
            <strong>There is a problem:</strong> {{ errorMessage }}
        </div>

        <template #buttons>
            <div class="cohortDialogButtons">
                <button type="button" class="cohortSecondaryButton" :disabled="isSaving" @click="dialog.close()">
                    Cancel
                </button>
                <button
                    type="submit"
                    form="moveStudentForm"
                    class="cohortPrimaryButton"
                    :disabled="otherCohorts.length === 0 || isSaving"
                >
                    Remove and Move
                </button>
            </div>
        </template>
    </PopupDialog>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import PopupDialog from './popupDialog.vue';
import { moveStudents } from '../../services/cohortService';

const props = defineProps({
    // the cohort the student is in now
    cohort: {
        type: Object,
        required: true,
    },
    student: {
        type: Object,
        required: true,
    },
    cohorts: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['close', 'saved']);

const dialog = ref(null);
const newCohortID = ref('');
const isSaving = ref(false);
const errorMessage = ref('');
// only a problem with the chosen cohort marks the box as wrong, not a lost connection or a stale student
const isCohortInvalid = ref(false);

const otherCohorts = computed(() => props.cohorts.filter((other) => other.cohortID !== props.cohort.cohortID));
const newCohortIsDisabled = computed(
    () => otherCohorts.value.find((other) => other.cohortID === newCohortID.value)?.isActive === false,
);

onMounted(() => {
    dialog.value.open();
});

async function move() {
    errorMessage.value = '';
    isCohortInvalid.value = false;

    if (newCohortID.value === '') {
        errorMessage.value = 'Select a cohort to move this student to';
        isCohortInvalid.value = true;
        return;
    }

    isSaving.value = true;

    try {
        emit('saved', await moveStudents(newCohortID.value, [props.student.studentNumber]));
        dialog.value.close();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || `Unable to move ${props.student.name}`;
        isCohortInvalid.value = error.response?.data?.field === 'cohortID';
    } finally {
        isSaving.value = false;
    }
}
</script>
