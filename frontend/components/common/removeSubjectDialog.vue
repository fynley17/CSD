<!-- Pop-up asking an admin to confirm taking a subject out of a cohort -->
<template>
    <PopupDialog ref="dialog" title="Remove Subject?" @close="emit('close')">
        <div class="cohortDialogConfirm">
            <i class="bi bi-exclamation-circle cohortDialogIcon" aria-hidden="true"></i>
            <h3>{{ subject.subjectName }}</h3>
            <p>
                Students in {{ cohort.cohortName }} will no longer see this subject's {{ testsText }}. Their past
                results are kept.
            </p>
            <p class="mb-0">Are you sure you want to remove this subject from the cohort?</p>
        </div>

        <template #buttons>
            <div class="cohortDialogButtons">
                <button
                    type="button"
                    class="cohortSecondaryButton btn btn-outline-primary"
                    :disabled="isSaving"
                    @click="dialog.close()"
                >
                    Cancel
                </button>
                <button type="button" class="cohortPrimaryButton btn btn-primary" :disabled="isSaving" @click="remove">
                    Remove Subject
                </button>
            </div>
        </template>
    </PopupDialog>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import PopupDialog from './popupDialog.vue';
import { removeSubject } from '../../services/cohortService';

const props = defineProps({
    cohort: {
        type: Object,
        required: true,
    },
    subject: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'saved', 'error']);

const dialog = ref(null);
const isSaving = ref(false);
const errorMessage = ref('');

const testsText = computed(() => `${props.subject.testCount} ${props.subject.testCount === 1 ? 'test' : 'tests'}`);

onMounted(() => {
    dialog.value.open();
});

async function remove() {
    errorMessage.value = '';
    isSaving.value = true;

    try {
        emit('saved', await removeSubject(props.cohort.cohortID, props.subject.subjectID));
        dialog.value.close();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || `Unable to remove ${props.subject.subjectName}`;
        emit('error', errorMessage.value);
    } finally {
        isSaving.value = false;
    }
}
</script>
