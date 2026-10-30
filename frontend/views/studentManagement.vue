<script setup>
import { computed, ref } from 'vue';
import StudentManagementTable from '../components/tables/studentManagementTable.vue';

// Reference the student table component so the parent view can access its filters.
const studentManagementTable = ref(null);

// Store the selected cohort, status and search term.
const cohortFilter = ref('');
const statusFilter = ref('');
const searchTerm = ref('');

// Retrieve the available cohort options from the student table component.
const cohorts = computed(() => studentManagementTable.value?.cohorts ?? []);

// Pass the selected cohort to the student table filter.
const filterByCohort = () => {
    studentManagementTable.value?.filterByCohort(cohortFilter.value);
};

// Pass the selected account status to the student table filter.
const filterByStatus = () => {
    studentManagementTable.value?.filterByStatus(statusFilter.value);
};

// Pass the search term to the student table's global search.
const searchStudents = () => {
    studentManagementTable.value?.searchStudents(searchTerm.value);
};
</script>

<template>
    <main class="studentManagement">
        <!-- Display the page title, description and Student Applications button. -->
        <div class="pageHeading">
            <div>
                <h1>Student Management</h1>
                <p>Manage student accounts, approvals and account status</p>
            </div>

            <button type="button" class="secondaryButton">Student Applications</button>
        </div>

        <!-- Provide independent filters for cohort, account status and student search. -->
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

        <!-- Display the student table and its records. -->
        <div class="tableSection">
            <StudentManagementTable ref="studentManagementTable" />
        </div>
    </main>
</template>

<style scoped>
/* Set the main page width, spacing and default typography. */
.studentManagement {
    max-width: var(--contentMaxWidth);
    min-height: 100%;
    margin: 0 auto;
    padding: var(--space2xl) var(--spaceLg);
    color: var(--colorText);
    font-family: var(--fontBody);
}

/* Arrange the page heading and applications button. */
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

/* Position the cohort filter, status filter and search field in one row. */
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

/* Apply consistent styling to the filter dropdowns and search input. */
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

/* Style the Student Applications button. */
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

/* Change the button colours when hovered over. */
.secondaryButton:hover {
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

/* Make keyboard focus visible on buttons and form controls. */
button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

/* Hide labels visually while keeping them available to screen readers. */
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

/* Adjust the filter layout for medium-sized screens. */
@media (max-width: 900px) {
    .tableToolbar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--spaceMd);
    }
}

/* Stack the heading and filters vertically on smaller screens. */
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
