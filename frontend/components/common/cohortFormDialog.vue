<!-- Pop-up for a cohort's name and status, creates one unless a cohort is passed in to edit -->
<template>
    <PopupDialog ref="dialog" :title="cohort ? 'Edit Cohort' : 'Create Cohort'" @close="emit('close')">
        <form id="cohortForm" novalidate @submit.prevent="save">
            <label class="cohortDialogLabel" for="cohortName">Cohort name</label>
            <input
                id="cohortName"
                ref="nameInput"
                v-model="cohortName"
                class="cohortDialogSearch"
                type="text"
                maxlength="50"
                autocomplete="off"
                required
                :aria-invalid="errorMessage ? 'true' : null"
                :aria-describedby="errorMessage ? 'cohortNameError' : null"
            />

            <label class="cohortDialogLabel mt-3" for="cohortStatus">Status</label>
            <select
                id="cohortStatus"
                v-model="status"
                class="cohortDialogSearch"
                :aria-describedby="status === 'disabled' ? 'cohortStatusNote' : null"
            >
                <option value="enabled">Enabled</option>
                <option value="disabled">Disabled</option>
            </select>
            <p v-if="status === 'disabled'" id="cohortStatusNote" class="cohortDialogNote">
                Students in a disabled cohort can't log in, its subjects are archived and it can't be chosen when
                registering.
            </p>
        </form>

        <div v-if="errorMessage" id="cohortNameError" class="alert alert-danger mt-3 mb-0" role="alert">
            <strong>There is a problem:</strong> {{ errorMessage }}
        </div>

        <template #buttons>
            <div class="cohortDialogButtons">
                <button type="button" class="cohortSecondaryButton" :disabled="isSaving" @click="dialog.close()">
                    Cancel
                </button>
                <button type="submit" form="cohortForm" class="cohortPrimaryButton" :disabled="isSaving">
                    {{ cohort ? 'Save Changes' : 'Create Cohort' }}
                </button>
            </div>
        </template>
    </PopupDialog>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import PopupDialog from './popupDialog.vue';
import { createCohort, updateCohort } from '../../services/cohortService';

const props = defineProps({
    // null when creating a new cohort
    cohort: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const dialog = ref(null);
const nameInput = ref(null);
const cohortName = ref(props.cohort?.cohortName ?? '');
const status = ref(props.cohort && !props.cohort.isActive ? 'disabled' : 'enabled');
const isSaving = ref(false);
const errorMessage = ref('');

onMounted(() => {
    dialog.value.open();
    nameInput.value.focus();
});

async function save() {
    errorMessage.value = '';

    // PHP checks this too, this just saves a trip for the most common mistake
    if (cohortName.value.trim() === '') {
        errorMessage.value = 'Enter a cohort name';
        return;
    }

    isSaving.value = true;

    try {
        const isActive = status.value === 'enabled';
        const savedCohort = props.cohort
            ? await updateCohort(props.cohort.cohortID, cohortName.value, isActive)
            : await createCohort(cohortName.value, isActive);
        emit('saved', savedCohort);
        dialog.value.close();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Unable to save the cohort';
    } finally {
        isSaving.value = false;
    }
}
</script>
