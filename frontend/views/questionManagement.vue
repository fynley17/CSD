<script setup>
import { computed, ref } from 'vue';
import QuestionManagementTable from '../components/tables/questionManagementTable.vue';

// Store the table reference so the page can apply filters and searches.
const questionTable = ref(null);

// Track the selected subject and the text entered into the search box.
const subjectFilter = ref('');
const searchTerm = ref('');

// Retrieve the available subjects from the question table for the dropdown.
const subjects = computed(() => questionTable.value?.subjects ?? []);

// Apply the selected subject filter to the Question table.
const filterBySubject = () => {
    questionTable.value?.filterBySubject(subjectFilter.value);
};

// Search the questions using the DataTables search API.
const searchQuestions = () => {
    questionTable.value?.searchQuestions(searchTerm.value);
};
</script>

<template>
    <main class="questionManagement">
        <!-- Page heading and short description. -->
        <div class="pageHeading">
            <div>
                <h1>Question Management</h1>
                <p>Create, edit and manage questions</p>
            </div>
        </div>

        <!-- Toolbar containing the subject filter and search input. -->
        <div class="tableToolbar">
            <label class="subjectFilter">
                <span class="visuallyHidden">Filter questions by subject</span>

                <!-- Populate the dropdown from the subjects returned by the table. -->
                <select v-model="subjectFilter" @change="filterBySubject">
                    <option value="">All subjects</option>
                    <option v-for="subject in subjects" :key="subject" :value="subject">
                        {{ subject }}
                    </option>
                </select>
            </label>

            <label class="questionSearch">
                <span class="visuallyHidden">Search questions</span>

                <!-- Search the table as the user types. -->
                <input v-model="searchTerm" type="search" placeholder="Search questions..." @input="searchQuestions" />
            </label>
        </div>

        <!-- Display the questions table, including its pagination controls. -->
        <div class="tableSection">
            <QuestionManagementTable ref="questionTable" />

            <!-- Align the create button below the table, on the right. -->
            <div class="tableFooter">
                <button type="button" class="primaryButton">
                    <span aria-hidden="true">＋</span>
                    Create New Question
                </button>
            </div>
        </div>
    </main>
</template>

<style scoped>
/* Centre the page and apply the shared application typography and spacing. */
.questionManagement {
    width: 100%;
    max-width: var(--contentMaxWidth);
    min-height: 100%;
    margin: 0 auto;
    padding: var(--space2xl) var(--spaceLg);
    color: var(--colorText);
    font-family: var(--fontBody);
}

/* Position the page title and description consistently with other management pages. */
.pageHeading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceMd);
    margin-bottom: var(--space2xl);
}

/* Apply the shared heading font and brand colour. */
.pageHeading h1 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

/* Style the supporting description beneath the page heading. */
.pageHeading p {
    margin: var(--spaceXs) 0 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

/* Place the subject filter and search input on the same row. */
.tableToolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceLg);
    margin-bottom: var(--spaceMd);
}

/* Allow both toolbar controls to shrink within the available space. */
.subjectFilter,
.questionSearch {
    flex: 1;
    min-width: 0;
}

/* Keep the subject dropdown wider than the search input. */
.subjectFilter {
    max-width: 405px;
}

/* Align the search box to the right and limit its width. */
.questionSearch {
    max-width: 320px;
    margin-left: auto;
}

/* Use consistent sizing, borders and typography for both toolbar controls. */
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

/* Keep the table section within the available page width. */
.tableSection {
    width: 100%;
    min-width: 0;
}

/* Align the create button beneath the table to the right. */
.tableFooter {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--spaceMd);
}

/* Apply the shared primary button styling. */
.primaryButton {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    background: var(--colorPrimary);
    color: var(--colorSurface);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

/* Provide visual feedback when the primary button is hovered over. */
.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

/* Make keyboard focus visible on interactive controls. */
button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

/* Hide labels visually while keeping them available to assistive technology. */
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

/* Stack the toolbar controls on smaller screens for easier use. */
@media (max-width: 639px) {
    .questionManagement {
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

    /* Allow both controls to use the full available width on mobile. */
    .subjectFilter,
    .questionSearch {
        max-width: none;
    }

    /* Make the create button easier to tap on smaller screens. */
    .tableFooter .primaryButton {
        width: 100%;
    }
}
</style>
