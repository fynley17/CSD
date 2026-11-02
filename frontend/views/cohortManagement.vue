<!-- Cohort management, where admins look after cohorts and who's in them -->
<template>
    <section class="cohortManagement">
        <h1>Cohort Management</h1>
        <p class="cohortManagementIntro">Create and manage cohorts, the students in them and the subjects they study</p>

        <div class="cohortToolbar">
            <label>
                <span class="visually-hidden">Search cohorts</span>
                <input v-model="searchText" type="search" placeholder="Search cohorts..." />
            </label>

            <label>
                <span class="visually-hidden">Filter cohorts by status</span>
                <select v-model="statusFilter">
                    <option value="">Status</option>
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                </select>
            </label>

            <label>
                <span class="visually-hidden">Sort cohorts</span>
                <select v-model="sortBy">
                    <option value="">Sort by</option>
                    <option value="nameAsc">Name (A-Z)</option>
                    <option value="nameDesc">Name (Z-A)</option>
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                </select>
            </label>

            <button type="button" class="cohortPrimaryButton cohortCreateButton">
                <span aria-hidden="true">+</span> Create Cohort
            </button>
        </div>

        <h2>Students in each cohort</h2>
        <CohortStudentsTable :cohorts="visibleCohorts" @toggleStatus="toggleStatus" />

        <h2>Subjects for each cohort</h2>
        <CohortSubjectsTable :cohorts="visibleCohorts" />
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import CohortStudentsTable from '../components/tables/cohortStudentsTable.vue';
import CohortSubjectsTable from '../components/tables/cohortSubjectsTable.vue';
import { sampleCohorts } from '../utils/sampleCohorts';

// a copy of the sample data, so changes last until the page is left but don't touch the original
const allCohorts = ref(sampleCohorts.map((cohort) => ({ ...cohort })));
const searchText = ref('');
const statusFilter = ref('');
const sortBy = ref('');

const sorters = {
    nameAsc: (a, b) => a.cohortName.localeCompare(b.cohortName),
    nameDesc: (a, b) => b.cohortName.localeCompare(a.cohortName),
    // the dates are YYYY-MM-DD, so they sort correctly as text
    newest: (a, b) => b.createdDate.localeCompare(a.createdDate),
    oldest: (a, b) => a.createdDate.localeCompare(b.createdDate),
};

// the search, filter and sort apply to both tables
const visibleCohorts = computed(() => {
    const search = searchText.value.trim().toLowerCase();
    const cohorts = allCohorts.value.filter(
        (cohort) =>
            cohort.cohortName.toLowerCase().includes(search) &&
            (statusFilter.value === '' || cohort.isActive === (statusFilter.value === 'active')),
    );

    return sortBy.value ? cohorts.sort(sorters[sortBy.value]) : cohorts;
});

function toggleStatus(cohort) {
    cohort.isActive = !cohort.isActive;
}
</script>

<style scoped>
.cohortManagement {
    max-width: var(--contentMaxWidth);
    margin: 0 auto;
    padding: var(--spaceLg) var(--spaceMd);
    color: var(--colorText);
}

.cohortManagement h1 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

.cohortManagement h2 {
    margin: var(--spaceXl) 0 var(--spaceMd);
    color: var(--colorText);
    font-size: var(--fontSizeH3);
    font-weight: 700;
}

.cohortManagementIntro {
    margin: var(--spaceXs) 0 var(--spaceLg);
    color: var(--colorTextSecondary);
}

/* stacked on phones, one row from tablet up with Create Cohort pushed to the right */
.cohortToolbar {
    display: grid;
    gap: var(--spaceMd);
}

.cohortToolbar input,
.cohortToolbar select {
    width: 100%;
    min-height: var(--minTouchTarget);
    padding: var(--spaceSm);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font: inherit;
}

.cohortToolbar input:focus-visible,
.cohortToolbar select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

@media (min-width: 768px) {
    .cohortManagement {
        padding: var(--space2xl) var(--spaceLg);
    }

    .cohortToolbar {
        grid-template-columns: 1.4fr 1fr 1fr auto;
        align-items: center;
    }
}
</style>
