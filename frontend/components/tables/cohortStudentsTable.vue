<!-- Students in each cohort. The action buttons aren't connected to PHP yet -->
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
            <td class="cohortActions">
                <button type="button" class="cohortActionButton" :aria-label="`Edit ${cohort.cohortName}`">Edit</button>
                <button
                    type="button"
                    class="btn btn-sm tableActionButton"
                    :class="cohort.isActive ? 'tableDisableButton' : 'tableEnableButton'"
                    :aria-label="`${cohort.isActive ? 'Disable' : 'Enable'} ${cohort.cohortName}`"
                >
                    {{ cohort.isActive ? 'Disable' : 'Enable' }}
                </button>
                <!-- a cohort can only be deleted once it has no students or subjects -->
                <button
                    type="button"
                    class="cohortActionButton"
                    :aria-label="`Delete ${cohort.cohortName}`"
                    :disabled="cohort.students.length > 0 || cohort.subjects.length > 0"
                >
                    Delete
                </button>
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
                    >
                        <span aria-hidden="true">+</span> Add students
                    </button>
                </div>

                <p v-if="cohort.students.length === 0" class="cohortDetailsEmpty">No students in this cohort yet</p>

                <template v-else>
                    <table class="cohortDetailsTable">
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
                                <td>{{ student.lastLogin || 'Never' }}</td>
                                <td>
                                    <span class="statusPill" :class="studentStatusClass(student)">
                                        {{ student.status }}
                                    </span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="cohortActionButton"
                                        :aria-label="`Remove from cohort: ${student.name}`"
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
</script>
