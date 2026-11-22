<template>
    <main class="container py-4">
        <h1>Your tests to complete</h1>

        <p v-if="isLoading" class="hintText" role="status">Loading tests...</p>
        <p v-else-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="tests.length === 0" class="hintText">There are no tests available right now.</p>

        <template v-else>
            <!-- tabs on the left, subject and search on the right of the same line, like Teams assignments
                 on phones the subject and search go above the tabs, there isn't room for one line -->
            <div class="testTabsBar mt-4">
                <!-- Upcoming and Overdue tabs. The arrow keys move between them,
                     only the chosen tab is in the Tab order, the standard pattern screen readers expect -->
                <div class="testTabs" role="tablist" aria-label="Tests to complete">
                    <button
                        v-for="tab in tabs"
                        :id="`${tab.key}Tab`"
                        :key="tab.key"
                        :ref="(element) => (tabButtons[tab.key] = element)"
                        type="button"
                        role="tab"
                        class="testTab"
                        :class="{ testTabActive: activeTab === tab.key }"
                        :aria-selected="activeTab === tab.key ? 'true' : 'false'"
                        aria-controls="testTabPanel"
                        :tabindex="activeTab === tab.key ? 0 : -1"
                        @click="selectTab(tab.key)"
                        @keydown="moveBetweenTabs"
                    >
                        {{ tab.label }}
                        <span class="testTabCount">{{ tab.tests.length }}</span>
                    </button>
                </div>

                <!-- they narrow both tabs so the counts stay right, the search box is furthest right.
                     The labels are only hidden on screen, screen readers still read them out -->
                <div class="testTabsFilters">
                    <TableFilter
                        id="testSelectionSubject"
                        v-model="subjectFilter"
                        label="Subject"
                        hideLabel
                        :options="nameOptions(subjects, 'All subjects')"
                    />
                    <TableSearch id="testSelectionSearch" v-model="searchText" placeholder="Search tests" hideLabel />
                </div>
            </div>

            <div id="testTabPanel" role="tabpanel" :aria-labelledby="`${activeTab}Tab`" class="pt-4">
                <!-- nothing matches the search or subject, so offer a quick way back to every test -->
                <div v-if="tabTests.length === 0 && isFiltering">
                    <p class="hintText" role="status">
                        No {{ activeTabDetails.label.toLowerCase() }} tests match your search.
                    </p>
                    <button type="button" class="btn btn-outline-secondary btn-sm clearFilters" @click="clearFilters">
                        Clear search and filter
                    </button>
                </div>
                <p v-else-if="tabTests.length === 0" class="hintText mb-0">{{ activeTabDetails.emptyText }}</p>

                <template v-else>
                    <!-- every card is the same size, the tallest card sets the height of every row -->
                    <div class="testCardGrid">
                        <TestCard v-for="test in visibleTests" :key="test.testID" :test="test" @start="startTest" />
                    </div>

                    <!-- two full rows per page, so the completed tests below are never pushed too far down -->
                    <ListPagination
                        v-model:page="currentPage"
                        :pageSize="pageSize"
                        :total="tabTests.length"
                        :label="`${activeTabDetails.label} tests pages`"
                    />
                </template>
            </div>
        </template>

        <section class="mt-5" aria-labelledby="completedTestsHeading">
            <h2 id="completedTestsHeading">Completed tests</h2>

            <p v-if="isLoadingCompleted" class="hintText" role="status">Loading completed tests...</p>
            <p v-else-if="completedLoadError" class="alert alert-danger" role="alert">{{ completedLoadError }}</p>
            <p v-else-if="completedTests.length === 0" class="hintText">You have not completed any tests yet.</p>

            <CompletedTestList v-else :tests="completedTests" />
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import CompletedTestList from '../components/common/completedTestList.vue';
import ListPagination from '../components/common/listPagination.vue';
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import TestCard from '../components/common/testCard.vue';
import { getCompletedTests } from '../services/resultService';
import { getAvailableTests } from '../services/testService';
import { nameOptions } from '../utils/filterOptions';

const router = useRouter();
const tests = ref([]);

// The testing page loads the questions for the chosen test from its ID in the URL.
function startTest(test) {
    router.push({ name: 'testing', params: { testID: test.testID } });
}
const isLoading = ref(true);
const loadError = ref('');

// the subjects the student has tests in, for the dropdown
const subjects = computed(() =>
    [...new Set(tests.value.map((test) => test.subjectName))].sort((a, b) => a.localeCompare(b)),
);
const searchText = ref('');
const subjectFilter = ref('');
const isFiltering = computed(() => searchText.value.trim() !== '' || subjectFilter.value !== '');

// the search looks at everything written on the card, upper or lower case doesn't matter
const filteredTests = computed(() => {
    const search = searchText.value.trim().toLowerCase();

    return tests.value.filter((test) => {
        if (subjectFilter.value && test.subjectName !== subjectFilter.value) {
            return false;
        }
        if (search === '') {
            return true;
        }
        return [test.testName, test.testDescription, test.subjectName, test.createdByName]
            .filter(Boolean)
            .some((text) => text.toLowerCase().includes(search));
    });
});

function clearFilters() {
    searchText.value = '';
    subjectFilter.value = '';
}

// Upcoming is every test that can still earn points, including ones with no due date.
// The backend has already sorted them, soonest due first.
const tabs = computed(() => [
    {
        key: 'upcoming',
        label: 'Upcoming',
        tests: filteredTests.value.filter((test) => !test.isOverdue),
        emptyText: 'You have no upcoming tests.',
    },
    {
        key: 'overdue',
        label: 'Overdue',
        tests: filteredTests.value.filter((test) => test.isOverdue),
        emptyText: 'You have no overdue tests.',
    },
]);
const activeTab = ref('upcoming');
const tabButtons = {};
const activeTabDetails = computed(() => tabs.value.find((tab) => tab.key === activeTab.value));
const tabTests = computed(() => activeTabDetails.value.tests);

// each tab starts on its first page
function selectTab(key) {
    activeTab.value = key;
    page.value = 1;
}

// Left and Right move to the other tab, Home and End go to the first or last
function moveBetweenTabs(event) {
    const keys = tabs.value.map((tab) => tab.key);
    const index = keys.indexOf(activeTab.value);
    const nextIndex = {
        ArrowRight: (index + 1) % keys.length,
        ArrowLeft: (index - 1 + keys.length) % keys.length,
        Home: 0,
        End: keys.length - 1,
    }[event.key];

    if (nextIndex === undefined) {
        return;
    }

    event.preventDefault();
    selectTab(keys[nextIndex]);
    tabButtons[keys[nextIndex]]?.focus();
}

// 3 columns on desktop, 2 on tablets and 1 on phones, the same breakpoints as the grid's CSS below.
// A page is two full rows, so 6, 4 or 2 cards.
const ROWS_PER_PAGE = 2;
const tabletQuery = '(max-width: 991.98px)';
const phoneQuery = '(max-width: 575.98px)';
const columnCount = ref(3);
const mediaQueries = [];

function updateColumnCount() {
    // the first test on screen, so resizing keeps it in view rather than jumping back to page 1
    const firstShownIndex = (currentPage.value - 1) * pageSize.value;

    if (window.matchMedia(phoneQuery).matches) {
        columnCount.value = 1;
    } else if (window.matchMedia(tabletQuery).matches) {
        columnCount.value = 2;
    } else {
        columnCount.value = 3;
    }

    page.value = Math.floor(firstShownIndex / pageSize.value) + 1;
}

const pageSize = computed(() => columnCount.value * ROWS_PER_PAGE);
const page = ref(1);
const pageCount = computed(() => Math.ceil(tabTests.value.length / pageSize.value));

// stays on a real page if the list gets shorter
const currentPage = computed({
    get: () => Math.min(page.value, Math.max(pageCount.value, 1)),
    set: (value) => {
        page.value = value;
    },
});

// a new search or subject starts from the first page, so the matches aren't hidden on a later one
watch([searchText, subjectFilter], () => {
    page.value = 1;
});

const visibleTests = computed(() =>
    tabTests.value.slice((currentPage.value - 1) * pageSize.value, currentPage.value * pageSize.value),
);

const completedTests = ref([]);
const isLoadingCompleted = ref(true);
const completedLoadError = ref('');

async function loadAvailableTests() {
    try {
        tests.value = await getAvailableTests();
    } catch (error) {
        loadError.value = error.response?.data?.error || 'Unable to load tests. Please try again later.';
    } finally {
        isLoading.value = false;
    }
}

async function loadCompletedTests() {
    try {
        completedTests.value = await getCompletedTests();
    } catch (error) {
        completedLoadError.value =
            error.response?.data?.error || 'Unable to load completed tests. Please try again later.';
    } finally {
        isLoadingCompleted.value = false;
    }
}

onMounted(() => {
    // matchMedia is missing in some test environments, the page then keeps 3 columns
    if (typeof window.matchMedia === 'function') {
        updateColumnCount();
        for (const query of [tabletQuery, phoneQuery]) {
            const mediaQuery = window.matchMedia(query);
            mediaQuery.addEventListener('change', updateColumnCount);
            mediaQueries.push(mediaQuery);
        }
    }

    // Load both lists side by side so a failure in one does not hide the other.
    return Promise.all([loadAvailableTests(), loadCompletedTests()]);
});

onUnmounted(() => {
    for (const mediaQuery of mediaQueries) {
        mediaQuery.removeEventListener('change', updateColumnCount);
    }
});
</script>

<style scoped>
/* the clear button is as tall as the other buttons on the page, so it's easy to tap */
.clearFilters {
    min-height: var(--minTouchTarget);
}

/* One line: tabs on the left, subject and search on the right, all sitting on the divider line. */
.testTabsBar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: var(--spaceMd);
    border-bottom: 1px solid var(--colorDivider);
}

/* the chosen tab is underlined in the brand colour, like Teams */
.testTabs {
    display: flex;
    gap: var(--spaceLg);
}

.testTabsFilters {
    display: flex;
    align-items: center;
    gap: var(--spaceSm);
    padding-bottom: var(--spaceSm);
}

/* .tableSearch is the outside of the TableSearch component, so scoped styles reach it without :deep */
.testTabsFilters .tableSearch {
    width: 16rem;
}

/* phones and small tablets: subject and search go above the tabs, each taking half the width,
   and the divider line moves to sit under the tabs */
@media (max-width: 767.98px) {
    .testTabsBar {
        flex-direction: column;
        align-items: stretch;
        border-bottom: 0;
    }

    .testTabsFilters {
        order: -1;
        padding-bottom: 0;
    }

    .testTabsFilters > * {
        flex: 1 1 0;
        min-width: 0;
    }

    .testTabsFilters .tableSearch {
        width: auto;
    }

    .testTabs {
        border-bottom: 1px solid var(--colorDivider);
    }
}

.testTab {
    display: inline-flex;
    align-items: center;
    gap: var(--spaceSm);
    min-height: var(--minTouchTarget);
    padding: 0 var(--spaceXs);
    border: 0;
    border-bottom: 3px solid transparent;
    background: none;
    color: var(--colorTextSecondary);
    font-weight: 600;
}

.testTab:hover {
    color: var(--colorText);
}

/* bold text and the underline both mark the chosen tab, so it isn't shown by colour alone */
.testTabActive {
    border-bottom-color: var(--colorBrand);
    color: var(--colorBrand);
    font-weight: 700;
}

.testTabCount {
    min-width: 1.75rem;
    padding: 0 var(--spaceSm);
    border-radius: var(--radiusPill);
    background: var(--colorSurfaceAlt);
    color: var(--colorText);
    font-size: var(--fontSizeSmall);
    text-align: center;
}

.testTabActive .testTabCount {
    background: var(--colorBrand);
    color: var(--colorSurface);
}

/* 3 columns on desktop, 2 on tablets and 1 on phones, the same breakpoints as Bootstrap's lg and sm.
   Every row is as tall as the tallest card and each card fills its cell, so all the cards match. */
.testCardGrid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-auto-rows: 1fr;
    gap: var(--spaceLg);
}

@media (max-width: 991.98px) {
    .testCardGrid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    .testCardGrid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
