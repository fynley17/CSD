<!-- Students in each cohort, the page shows the pop-ups for its action buttons -->
<template>
    <CohortExpandableTable
        :cohorts="cohorts"
        label="Students in each cohort"
        :columnCount="7"
        :sortValues="cohortSortValues"
        :toolbarSort="toolbarSort"
    >
        <template #headings="{ sort, toggleSort }">
            <SortableHeading column="name" :sort="sort" @sort="toggleSort">Cohort name</SortableHeading>
            <SortableHeading column="students" :sort="sort" @sort="toggleSort">Students</SortableHeading>
            <SortableHeading column="subjects" :sort="sort" @sort="toggleSort">Subjects</SortableHeading>
            <SortableHeading column="created" :sort="sort" @sort="toggleSort">Created</SortableHeading>
            <SortableHeading column="status" :sort="sort" @sort="toggleSort">Status</SortableHeading>
            <th scope="col">Actions</th>
        </template>

        <template #cells="{ cohort }">
            <td>{{ cohort.students.length }}</td>
            <td>{{ cohort.subjects.length }}</td>
            <td>{{ formatDate(cohort.createdDate) }}</td>
            <td>
                <span class="statusPill" :class="cohort.isActive ? 'statusActive' : 'statusDisabled'">
                    {{ cohort.isActive ? 'Active' : 'Disabled' }}
                </span>
            </td>
            <td>
                <!-- in its own div, a flex td stops being a table cell and no longer centres with its row -->
                <div class="cohortActions">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        :aria-label="`Edit ${cohort.cohortName}`"
                        @click="emit('edit', cohort)"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        :aria-label="`${cohort.isActive ? 'Disable' : 'Enable'} ${cohort.cohortName}`"
                        @click="emit('toggleStatus', cohort)"
                    >
                        {{ cohort.isActive ? 'Disable' : 'Enable' }}
                    </button>
                    <!-- always clickable, the pop-up explains why a cohort with students or subjects can't go -->
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        :aria-label="`Delete ${cohort.cohortName}`"
                        @click="emit('delete', cohort)"
                    >
                        Delete
                    </button>
                </div>
            </td>
        </template>

        <template #details="{ cohort }">
            <div class="cohortDetails">
                <div class="cohortDetailsHeader">
                    <h3>Students in {{ cohort.cohortName }} ({{ cohort.students.length }})</h3>
                    <button
                        type="button"
                        class="cohortPrimaryButton"
                        :aria-label="`Add students to ${cohort.cohortName}`"
                        @click="emit('addStudents', cohort)"
                    >
                        <span aria-hidden="true">+</span> Add students
                    </button>
                </div>

                <p v-if="cohort.students.length === 0" class="cohortDetailsEmpty">No students in this cohort yet</p>

                <template v-else>
                    <table class="table table-sm align-middle mb-0 cohortDetailsTable">
                        <caption class="visually-hidden">
                            Students in
                            {{
                                cohort.cohortName
                            }}
                        </caption>
                        <thead>
                            <tr>
                                <SortableHeading column="studentNumber" :sort="studentSort" @sort="toggleStudentSort">
                                    Student number
                                </SortableHeading>
                                <SortableHeading column="name" :sort="studentSort" @sort="toggleStudentSort">
                                    Name
                                </SortableHeading>
                                <SortableHeading column="email" :sort="studentSort" @sort="toggleStudentSort">
                                    Email
                                </SortableHeading>
                                <SortableHeading column="lastLogin" :sort="studentSort" @sort="toggleStudentSort">
                                    Last login
                                </SortableHeading>
                                <SortableHeading column="status" :sort="studentSort" @sort="toggleStudentSort">
                                    Status
                                </SortableHeading>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="student in shownStudents(cohort)" :key="student.studentNumber">
                                <td>{{ student.studentNumber }}</td>
                                <th scope="row">{{ student.name }}</th>
                                <td>{{ student.email }}</td>
                                <td>{{ formatLastLogin(student.lastLogin) }}</td>
                                <td>
                                    <span class="statusPill" :class="studentStatusClass(student)">
                                        {{ student.status }}
                                    </span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        :aria-label="`Remove from cohort: ${student.name}`"
                                        @click="emit('moveStudent', cohort, student)"
                                    >
                                        Remove from cohort
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="cohort.students.length > PREVIEW_SIZE" class="cohortDetailsFooter">
                        <span> Showing {{ shownStudents(cohort).length }} of {{ cohort.students.length }} </span>
                        <button
                            type="button"
                            class="cohortActionButton"
                            :aria-expanded="isShowingAll(cohort)"
                            :aria-label="showAllLabel(cohort)"
                            @click="toggleShowAll(cohort)"
                        >
                            {{ isShowingAll(cohort) ? 'Show fewer' : 'View all' }}
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </CohortExpandableTable>
</template>

<script setup>
import { ref } from 'vue';
import { nextSort, sortRows } from '../../utils/sortRows';
import CohortExpandableTable from './cohortExpandableTable.vue';
import SortableHeading from './sortableHeading.vue';

const PREVIEW_SIZE = 3;

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

// the dates are YYYY-MM-DD, so they sort correctly as text
const cohortSortValues = {
    name: (cohort) => cohort.cohortName,
    students: (cohort) => cohort.students.length,
    subjects: (cohort) => cohort.subjects.length,
    created: (cohort) => cohort.createdDate,
    status: (cohort) => (cohort.isActive ? 'Active' : 'Disabled'),
};

// last login is YYYY-MM-DD HH:MM:SS from the database, so it sorts correctly as text, Never comes first
const studentSortValues = {
    studentNumber: (student) => student.studentNumber,
    name: (student) => student.name,
    email: (student) => student.email,
    lastLogin: (student) => student.lastLogin,
    status: (student) => student.status,
};

// one sort for every cohort's students, so they all stay in the same order when opened
const studentSort = ref({ key: '', direction: '' });

function toggleStudentSort(key) {
    studentSort.value = nextSort(studentSort.value, key);
}

const emit = defineEmits(['edit', 'toggleStatus', 'delete', 'addStudents', 'moveStudent']);

const showAllCohortIDs = ref(new Set());

function isShowingAll(cohort) {
    return showAllCohortIDs.value.has(cohort.cohortID);
}

function toggleShowAll(cohort) {
    if (isShowingAll(cohort)) {
        showAllCohortIDs.value.delete(cohort.cohortID);
    } else {
        showAllCohortIDs.value.add(cohort.cohortID);
    }
}

function showAllLabel(cohort) {
    return `${isShowingAll(cohort) ? 'Show fewer' : 'View all'} students in ${cohort.cohortName}`;
}

// sorted before cutting down to the preview, so the preview shows the top of the sorted list
function shownStudents(cohort) {
    const students = sortRows(cohort.students, studentSort.value, studentSortValues);

    return isShowingAll(cohort) ? students : students.slice(0, PREVIEW_SIZE);
}

function studentStatusClass(student) {
    return { Active: 'statusActive', Pending: 'statusPending' }[student.status] || 'statusDisabled';
}

// turns 2026-09-01 into 01/09/2026
function formatDate(date) {
    return date.split('-').reverse().join('/');
}

// turns 2026-09-29 09:42:10 into 29/09/2026, 09:42
function formatLastLogin(lastLogin) {
    if (!lastLogin) {
        return 'Never';
    }

    const [date, time] = lastLogin.split(' ');

    return `${formatDate(date)}, ${time.slice(0, 5)}`;
}
</script>
