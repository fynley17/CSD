<!-- Subjects for each cohort, the page shows the pop-ups for assigning and removing them -->
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
                            <th scope="col">Subject</th>
                            <th scope="col">Tests</th>
                            <th scope="col">Lecturers</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="subject in cohort.subjects" :key="subject.subjectID">
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
import CohortExpandableTable from './cohortExpandableTable.vue';

defineProps({
    cohorts: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['assign', 'remove']);
</script>
