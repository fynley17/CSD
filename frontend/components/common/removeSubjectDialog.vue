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

        <div v-if="errorMessage" class="alert alert-danger mt-3 mb-0" role="alert">
            <strong>There is a problem:</strong> {{ errorMessage }}
        </div>

        <template #buttons>
            <div class="cohortDialogButtons">
                <button type="button" class="cohortSecondaryButton" :disabled="isSaving" @click="dialog.close()">
                    Cancel
                </button>
                <button type="button" class="cohortPrimaryButton" :disabled="isSaving" @click="remove">
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

const emit = defineEmits(['close', 'saved']);

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
    } finally {
        isSaving.value = false;
    }
}
</script>
