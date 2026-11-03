<!-- Pop-up for choosing which unassigned subjects to add to a cohort -->
<template>
    <PopupDialog ref="dialog" :title="`Assign Subjects to ${cohort.cohortName}`" @close="emit('close')">
        <p v-if="isLoading" class="mb-0" role="status">Loading subjects</p>

        <template v-else-if="subjects.length > 0">
            <label class="d-block">
                <span class="visually-hidden">Search subjects</span>
                <input v-model="searchText" class="cohortDialogSearch" type="search" placeholder="Search subjects..." />
            </label>

            <table class="cohortDialogTable">
                <caption class="visually-hidden">
                    Subjects that can be assigned
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Subject</th>
                        <th scope="col" class="cohortDialogCount">Tests</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="visibleSubjects.length === 0">
                        <td colspan="2">No subjects match your search</td>
                    </tr>
                    <tr v-for="subject in visibleSubjects" :key="subject.subjectID">
                        <td>
                            <label class="cohortDialogOption">
                                <input v-model="selectedIDs" type="checkbox" :value="subject.subjectID" />
                                {{ subject.subjectName }}
                            </label>
                        </td>
                        <td class="cohortDialogCount">{{ subject.testCount }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="cohortDialogNote">
                Only subjects not assigned to a cohort are listed. Students in the cohort get access to the tests in
                these subjects.
            </p>
        </template>

        <p v-else-if="!errorMessage" class="mb-0">There are no unassigned subjects to add</p>

        <div v-if="errorMessage" class="alert alert-danger mt-3 mb-0" role="alert">
            <strong>There is a problem:</strong> {{ errorMessage }}
        </div>

        <template #buttons>
            <div class="cohortDialogButtons">
                <button type="button" class="cohortSecondaryButton" :disabled="isSaving" @click="dialog.close()">
                    Cancel
                </button>
                <button
                    type="button"
                    class="cohortPrimaryButton"
                    :disabled="selectedIDs.length === 0 || isSaving"
                    @click="assign"
                >
                    {{ assignLabel }}
                </button>
            </div>
        </template>
    </PopupDialog>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import PopupDialog from './popupDialog.vue';
import { assignSubjects, getUnassignedSubjects } from '../../services/cohortService';

const props = defineProps({
    cohort: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'saved']);

const dialog = ref(null);
const subjects = ref([]);
const selectedIDs = ref([]);
const searchText = ref('');
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');

const visibleSubjects = computed(() => {
    const search = searchText.value.trim().toLowerCase();

    return subjects.value.filter((subject) => subject.subjectName.toLowerCase().includes(search));
});

const assignLabel = computed(() => {
    const count = selectedIDs.value.length;

    return count === 0 ? 'Assign Subjects' : `Assign ${count} ${count === 1 ? 'Subject' : 'Subjects'}`;
});

onMounted(async () => {
    dialog.value.open();

    try {
        subjects.value = await getUnassignedSubjects();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Unable to load the subjects';
    } finally {
        isLoading.value = false;
    }
});

async function assign() {
    errorMessage.value = '';
    isSaving.value = true;

    try {
        emit('saved', await assignSubjects(props.cohort.cohortID, selectedIDs.value));
        dialog.value.close();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Unable to assign the subjects';
    } finally {
        isSaving.value = false;
    }
}
</script>
