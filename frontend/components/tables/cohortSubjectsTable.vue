<!-- Subjects for each cohort. The action buttons aren't connected to PHP yet -->
<template>
    <CohortExpandableTable :cohorts="cohorts" label="Subjects for each cohort" :columnCount="4">
        <template #headings>
            <th scope="col">Cohort name</th>
            <th scope="col">Subjects</th>
            <th scope="col">Status</th>
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
                        class="cohortPrimaryButton"
                        :aria-label="`Assign subjects to ${cohort.cohortName}`"
                    >
                        <span aria-hidden="true">+</span> Assign subjects
                    </button>
                </div>

                <p v-if="cohort.subjects.length === 0" class="cohortDetailsEmpty">No subjects for this cohort yet</p>

                <table v-else class="cohortDetailsTable">
                    <caption class="visually-hidden">
                        Subjects studied by
                        {{
                            cohort.cohortName
                        }}
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Subject</th>
                            <th scope="col">Tests</th>
                            <th scope="col">Lecturers</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="subject in cohort.subjects" :key="subject.subjectID">
                            <th scope="row">{{ subject.subjectName }}</th>
                            <td>{{ subject.testCount }}</td>
                            <td>{{ subject.lecturerNames || 'Not assigned' }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="cohortActionButton"
                                    :aria-label="`Remove from cohort: ${subject.subjectName}`"
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
import CohortExpandableTable from './cohortExpandableTable.vue';

defineProps({
    cohorts: {
        type: Array,
        required: true,
    },
});
</script>
