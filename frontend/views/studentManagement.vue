<script setup>
import { computed, ref } from 'vue';
import StudentManagementTable from '../components/tables/studentManagementTable.vue';

const studentManagementTable = ref(null);

const cohortFilter = ref('');
const statusFilter = ref('');
const searchTerm = ref('');

const cohorts = computed(() => studentManagementTable.value?.cohorts ?? []);

const filterByCohort = () => {
    studentManagementTable.value?.filterByCohort(cohortFilter.value);
};

const filterByStatus = () => {
    studentManagementTable.value?.filterByStatus(statusFilter.value);
};

const searchStudents = () => {
    studentManagementTable.value?.searchStudents(searchTerm.value);
};
</script>

<template>
    <main class="studentManagement">
        <div class="pageHeading">
            <div>
                <h1>Student Management</h1>
                <p>Manage student accounts, approvals and account status</p>
            </div>

            <button type="button" class="secondaryButton">Student Applications</button>
        </div>

        <div class="tableToolbar">
            <label class="studentFilter">
                <span class="visuallyHidden">Filter students by cohort</span>
                <select v-model="cohortFilter" @change="filterByCohort">
                    <option value="">All cohorts</option>
                    <option v-for="cohort in cohorts" :key="cohort" :value="cohort">
                        {{ cohort }}
                    </option>
                </select>
            </label>

            <label class="studentFilter">
                <span class="visuallyHidden">Filter students by status</span>
                <select v-model="statusFilter" @change="filterByStatus">
                    <option value="">All statuses</option>
                    <option value="Active">Active</option>
                    <option value="Pending">Pending</option>
                    <option value="Disabled">Disabled</option>
                </select>
            </label>

            <label class="studentSearch">
                <span class="visuallyHidden">Search students</span>
                <input v-model="searchTerm" type="search" placeholder="Search students..." @input="searchStudents" />
            </label>
        </div>

        <div class="tableSection">
            <StudentManagementTable ref="studentManagementTable" />
        </div>
    </main>
</template>

<style scoped>
.studentManagement {
    max-width: var(--contentMaxWidth);
    min-height: 100%;
    margin: 0 auto;
    padding: var(--space2xl) var(--spaceLg);
    color: var(--colorText);
    font-family: var(--fontBody);
}

.pageHeading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceMd);
    margin-bottom: var(--space2xl);
}

.pageHeading h1 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

.pageHeading p {
    margin: var(--spaceXs) 0 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

.tableToolbar {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr)) minmax(0, 1.3fr);
    align-items: center;
    gap: var(--spaceLg);
    margin-bottom: var(--spaceMd);
}

.tableToolbar label {
    min-width: 0;
}

.tableToolbar input,
.tableToolbar select {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    padding: var(--spaceSm);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font: inherit;
}

.secondaryButton {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorPrimary);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.secondaryButton:hover {
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

.visuallyHidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 900px) {
    .tableToolbar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--spaceMd);
    }
}

@media (max-width: 639px) {
    .studentManagement {
        padding: var(--spaceLg) var(--spaceMd);
    }

    .pageHeading {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: var(--spaceXl);
    }

    .tableToolbar {
        grid-template-columns: 1fr;
        gap: var(--spaceMd);
    }
}
</style>
