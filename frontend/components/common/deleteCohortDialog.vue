<!-- Pop-up for deleting a cohort. Only an empty cohort can go, one with students is offered disabling instead -->
<template>
    <PopupDialog ref="dialog" :title="canDelete ? 'Delete Cohort?' : 'Cannot Delete Cohort'" @close="emit('close')">
        <div class="cohortDialogConfirm">
            <i class="bi bi-exclamation-circle cohortDialogIcon" aria-hidden="true"></i>
            <h3>{{ cohort.cohortName }}</h3>

            <template v-if="canDelete">
                <p>This cohort has no students or subjects. Deleting it can't be undone.</p>
                <p class="mb-0">Are you sure you want to delete this cohort?</p>
            </template>

            <template v-else-if="studentCount === 0">
                <p class="mb-0">
                    This cohort has {{ subjectsText }}, so it can't be deleted. Remove its subjects from the cohort
                    first.
                </p>
            </template>

            <template v-else>
                <p>This cohort has {{ contentsText }}, so it can't be deleted.</p>
                <p v-if="cohort.isActive" class="mb-0">
                    You can disable it instead. Its students won't be able to log in and its subjects will be archived.
                </p>
                <p v-else class="mb-0">It's already disabled, so its students can't log in.</p>
            </template>
        </div>

        <div v-if="errorMessage" class="alert alert-danger mt-3 mb-0" role="alert">
            <strong>There is a problem:</strong> {{ errorMessage }}
        </div>

        <template #buttons>
            <div class="cohortDialogButtons">
                <button type="button" class="cohortSecondaryButton" :disabled="isSaving" @click="dialog.close()">
                    {{ canDelete || canDisable ? 'Cancel' : 'Close' }}
                </button>
                <button
                    v-if="canDelete"
                    type="button"
                    class="cohortPrimaryButton"
                    :disabled="isSaving"
                    @click="deleteTheCohort"
                >
                    Delete Cohort
                </button>
                <button
                    v-if="canDisable"
                    type="button"
                    class="cohortPrimaryButton"
                    :disabled="isSaving"
                    @click="disableTheCohort"
                >
                    Disable Cohort
                </button>
            </div>
        </template>
    </PopupDialog>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import PopupDialog from './popupDialog.vue';
import { deleteCohort, setCohortActive } from '../../services/cohortService';

const props = defineProps({
    cohort: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'deleted', 'disabled']);

const dialog = ref(null);
const isSaving = ref(false);
const errorMessage = ref('');

const studentCount = computed(() => props.cohort.students.length);
const subjectCount = computed(() => props.cohort.subjects.length);
const canDelete = computed(() => studentCount.value === 0 && subjectCount.value === 0);
const canDisable = computed(() => studentCount.value > 0 && props.cohort.isActive);

const subjectsText = computed(() => `${subjectCount.value} ${subjectCount.value === 1 ? 'subject' : 'subjects'}`);
const contentsText = computed(() => {
    const students = `${studentCount.value} ${studentCount.value === 1 ? 'student' : 'students'}`;

    return subjectCount.value === 0 ? students : `${students} and ${subjectsText.value}`;
});

onMounted(() => {
    dialog.value.open();
});

/**
 * Runs the save, then closes the pop-up, or stays open with the problem if it fails
 */
async function save(saving, fallbackError) {
    errorMessage.value = '';
    isSaving.value = true;

    try {
        await saving();
        dialog.value.close();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || fallbackError;
    } finally {
        isSaving.value = false;
    }
}

function deleteTheCohort() {
    return save(async () => {
        await deleteCohort(props.cohort.cohortID);
        emit('deleted', props.cohort);
    }, `Unable to delete ${props.cohort.cohortName}`);
}

function disableTheCohort() {
    return save(async () => {
        emit('disabled', await setCohortActive(props.cohort.cohortID, false));
    }, `Unable to disable ${props.cohort.cohortName}`);
}
</script>
