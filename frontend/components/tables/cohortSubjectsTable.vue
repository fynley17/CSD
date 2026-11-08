<!-- Subjects for each cohort, the page shows the pop-ups for assigning and removing them -->
<template>
    <CohortExpandableTable
        :cohorts="cohorts"
        label="Subjects for each cohort"
        :columnCount="4"
        :sortValues="cohortSortValues"
        :toolbarSort="toolbarSort"
    >
        <template #headings="{ sort, toggleSort }">
            <SortableHeading column="name" :sort="sort" @sort="toggleSort">Cohort name</SortableHeading>
            <SortableHeading column="subjects" :sort="sort" @sort="toggleSort">Subjects</SortableHeading>
            <SortableHeading column="status" :sort="sort" @sort="toggleSort">Status</SortableHeading>
        </template>

        <template #cells="{ cohort }">
            <td>{{ cohort.subjects.length }}</td>
            <td>
                <span class="statusPill" :class="cohort.isActive ? 'statusActive' : 'statusDisabled'">
                    {{ cohort.isActive ? 'Active' : 'Disabled' }}
                </span>
            </td>
        </template>

        <template #details="{ cohort }">
            <div class="cohortDetails">
                <div class="cohortDetailsHeader">
                    <h3>Subjects studied by {{ cohort.cohortName }} ({{ cohort.subjects.length }})</h3>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :aria-label="`Assign subjects to ${cohort.cohortName}`"
                        @click="emit('assign', cohort)"
                    >
                        <span aria-hidden="true">+</span> Assign subjects
                    </button>
                </div>

                <p v-if="cohort.subjects.length === 0" class="cohortDetailsEmpty">No subjects for this cohort yet</p>

                <table v-else class="table table-sm align-middle mb-0 cohortDetailsTable">
                    <caption class="visually-hidden">
                        Subjects studied by
                        {{
                            cohort.cohortName
                        }}
                    </caption>
                    <thead>
                        <tr>
                            <SortableHeading column="subject" :sort="subjectSort" @sort="toggleSubjectSort">
                                Subject
                            </SortableHeading>
                            <SortableHeading column="tests" :sort="subjectSort" @sort="toggleSubjectSort">
                                Tests
                            </SortableHeading>
                            <SortableHeading column="lecturers" :sort="subjectSort" @sort="toggleSubjectSort">
                                Lecturers
                            </SortableHeading>
                            <SortableHeading column="status" :sort="subjectSort" @sort="toggleSubjectSort">
                                Status
                            </SortableHeading>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="subject in sortedSubjects(cohort)" :key="subject.subjectID">
                            <th scope="row">{{ subject.subjectName }}</th>
                            <td>{{ subject.testCount }}</td>
                            <td>{{ subject.lecturerNames || 'Not assigned' }}</td>
                            <td>
                                <span class="statusPill" :class="subject.isActive ? 'statusActive' : 'statusArchived'">
                                    {{ subject.isActive ? 'Active' : 'Archived' }}
                                </span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    :aria-label="`Remove from cohort: ${subject.subjectName}`"
                                    @click="emit('remove', cohort, subject)"
                                >
                                    Remove from cohort
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </CohortExpandableTable>
</template>

<script setup>
import { ref } from 'vue';
import { nextSort, sortRows } from '../../utils/sortRows';
import CohortExpandableTable from './cohortExpandableTable.vue';
import SortableHeading from './sortableHeading.vue';

defineProps({
    cohorts: {
        type: Array,
        required: true,
    },
    // the Sort by drop down above the tables
    toolbarSort: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['assign', 'remove']);

const cohortSortValues = {
    name: (cohort) => cohort.cohortName,
    subjects: (cohort) => cohort.subjects.length,
    status: (cohort) => (cohort.isActive ? 'Active' : 'Disabled'),
};

const subjectSortValues = {
    subject: (subject) => subject.subjectName,
    tests: (subject) => subject.testCount,
    lecturers: (subject) => subject.lecturerNames || 'Not assigned',
    status: (subject) => (subject.isActive ? 'Active' : 'Archived'),
};

// one sort for every cohort's subjects, so they all stay in the same order when opened
const subjectSort = ref({ key: '', direction: '' });

function toggleSubjectSort(key) {
    subjectSort.value = nextSort(subjectSort.value, key);
}

function sortedSubjects(cohort) {
    return sortRows(cohort.subjects, subjectSort.value, subjectSortValues);
}
</script>
