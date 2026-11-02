<!-- Pop-up for choosing students to move into a cohort from the cohorts they're in now -->
<template>
    <PopupDialog ref="dialog" :title="`Add Students to ${cohort.cohortName}`" @close="emit('close')">
        <p v-if="isLoading" class="mb-0" role="status">Loading students</p>

        <template v-else-if="students.length > 0">
            <label class="d-block">
                <span class="visually-hidden">Search by name or student number</span>
                <input
                    v-model="searchText"
                    class="cohortDialogSearch"
                    type="search"
                    placeholder="Search by name or student number..."
                />
            </label>

            <table class="cohortDialogTable">
                <caption class="visually-hidden">
                    Students who can be added
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col" class="cohortDialogCount">Current cohort</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="visibleStudents.length === 0">
                        <td colspan="2">No students match your search</td>
                    </tr>
                    <tr v-for="student in visibleStudents" :key="student.studentNumber">
                        <td>
                            <label class="cohortDialogOption">
                                <input v-model="selectedNumbers" type="checkbox" :value="student.studentNumber" />
                                {{ student.name }} {{ student.studentNumber }}
                            </label>
                        </td>
                        <td class="cohortDialogCount">{{ student.currentCohort }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="cohortDialogNote">
                A student can only be in one cohort. Adding them here moves them out of their current cohort.
            </p>
            <p v-if="!cohort.isActive" class="cohortDialogNote">
                This cohort is disabled, so students added to it won't be able to log in.
            </p>
        </template>

        <p v-else-if="!errorMessage" class="mb-0">There are no students in other cohorts to add</p>

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
                    :disabled="selectedNumbers.length === 0 || isSaving"
                    @click="add"
                >
                    {{ addLabel }}
                </button>
            </div>
        </template>
    </PopupDialog>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import PopupDialog from './popupDialog.vue';
import { getStudentsOutsideCohort, moveStudents } from '../../services/cohortService';

const props = defineProps({
    cohort: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'saved']);

const dialog = ref(null);
const students = ref([]);
const selectedNumbers = ref([]);
const searchText = ref('');
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');

const visibleStudents = computed(() => {
    const search = searchText.value.trim().toLowerCase();

    return students.value.filter(
        (student) =>
            student.name.toLowerCase().includes(search) || student.studentNumber.toLowerCase().includes(search),
    );
});

const addLabel = computed(() => {
    const count = selectedNumbers.value.length;

    return count === 0 ? 'Add Students' : `Add ${count} ${count === 1 ? 'Student' : 'Students'}`;
});

onMounted(async () => {
    dialog.value.open();

    try {
        students.value = await getStudentsOutsideCohort(props.cohort.cohortID);
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Unable to load the students';
    } finally {
        isLoading.value = false;
    }
});

async function add() {
    errorMessage.value = '';
    isSaving.value = true;

    try {
        emit('saved', await moveStudents(props.cohort.cohortID, selectedNumbers.value));
        dialog.value.close();
    } catch (error) {
        errorMessage.value = error.response?.data?.error || 'Unable to add the students';
    } finally {
        isSaving.value = false;
    }
}
</script>
