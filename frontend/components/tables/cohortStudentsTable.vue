<!-- Students in each cohort, the page shows the pop-ups for its action buttons -->
<template>
    <CohortExpandableTable :cohorts="cohorts" label="Students in each cohort" :columnCount="7">
        <template #headings>
            <th scope="col">Cohort name</th>
            <th scope="col">Students</th>
            <th scope="col">Subjects</th>
            <th scope="col">Created</th>
            <th scope="col">Status</th>
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
                                <th scope="col">Student number</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Last login</th>
                                <th scope="col">Status</th>
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
import CohortExpandableTable from './cohortExpandableTable.vue';

const PREVIEW_SIZE = 3;

defineProps({
    cohorts: {
        type: Array,
        required: true,
    },
});

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

function shownStudents(cohort) {
    return isShowingAll(cohort) ? cohort.students : cohort.students.slice(0, PREVIEW_SIZE);
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
