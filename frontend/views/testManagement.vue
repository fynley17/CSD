<script setup>
import { computed, ref } from 'vue';
import TestManagementTable from '../components/tables/testManagementTable.vue';

const testManagementTable = ref(null);
const subjectFilter = ref('');
const searchTerm = ref('');

const subjects = computed(() => testManagementTable.value?.subjects ?? []);

const filterBySubject = () => {
    testManagementTable.value?.filterBySubject(subjectFilter.value);
};

const searchTests = () => {
    testManagementTable.value?.searchTests(searchTerm.value);
};
</script>

<template>
    <main class="testManagement">
        <div class="pageHeading">
            <div>
                <h1>Test Management</h1>
                <p>Create, edit and manage tests</p>
            </div>
        </div>

        <!-- Toolbar containing the subject filter and search input. -->
        <div class="tableToolbar">
            <label class="subjectFilter">
                <span class="visuallyHidden">Filter tests by subject</span>
                <select v-model="subjectFilter" @change="filterBySubject">
                    <option value="">All subjects</option>
                    <option v-for="subject in subjects" :key="subject" :value="subject">
                        {{ subject }}
                    </option>
                </select>
            </label>

            <label class="testSearch">
                <span class="visuallyHidden">Search tests</span>
                <input v-model="searchTerm" type="search" placeholder="Search tests..." @input="searchTests" />
            </label>
        </div>

        <!-- Display the test management table and its pagination. -->
        <div class="tableSection">
            <TestManagementTable ref="testManagementTable" />

            <!-- Keep the create button below the entire table. -->
            <div class="tableFooter">
                <button type="button" class="primaryButton"><span aria-hidden="true">＋</span> Create New Test</button>
            </div>
        </div>
    </main>
</template>

<style scoped>
.testManagement {
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
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceLg);
    margin-bottom: var(--spaceMd);
}

.subjectFilter,
.testSearch {
    flex: 1;
    min-width: 0;
}

.subjectFilter {
    max-width: 405px;
}

.testSearch {
    max-width: 320px;
    margin-left: auto;
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

.tableSection {
    width: 100%;
}

.tableFooter {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--spaceMd);
}

.primaryButton,
.secondaryButton {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.primaryButton {
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

.secondaryButton {
    background: var(--colorSurface);
    color: var(--colorPrimary);
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

@media (max-width: 639px) {
    .testManagement {
        padding: var(--spaceLg) var(--spaceMd);
    }

    .pageHeading {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: var(--spaceXl);
    }

    .tableToolbar {
        flex-direction: column;
        align-items: stretch;
        gap: var(--spaceMd);
    }

    .subjectFilter,
    .testSearch {
        max-width: none;
    }

    .tableFooter .primaryButton {
        width: 100%;
    }
}
</style>
