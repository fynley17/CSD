<template>
    <main class="container py-4">
        <h1>Your tests to complete</h1>

        <p v-if="isLoading" class="text-secondary" role="status">Loading tests...</p>
        <p v-else-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="tests.length === 0" class="text-secondary">There are no tests available right now.</p>

        <!-- each column stacks its own cards, so every card starts right under the one above it -->
        <div v-else class="testCardGrid mt-4" :style="{ '--testCardColumns': columnCount }">
            <div v-for="(column, index) in testColumns" :key="index" class="testCardColumn">
                <div v-for="test in column" :key="test.testID" class="testCardGridItem">
                    <TestCard :test="test" @start="startTest" />
                </div>
            </div>
        </div>

        <section class="mt-5" aria-labelledby="completedTestsHeading">
            <h2 id="completedTestsHeading">Completed tests</h2>

            <p v-if="isLoadingCompleted" class="text-secondary" role="status">Loading completed tests...</p>
            <p v-else-if="completedLoadError" class="alert alert-danger" role="alert">{{ completedLoadError }}</p>
            <p v-else-if="completedTests.length === 0" class="text-secondary">You have not completed any tests yet.</p>

            <CompletedTestList v-else :tests="completedTests" />
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import CompletedTestList from '../components/common/completedTestList.vue';
import TestCard from '../components/common/testCard.vue';
import { getCompletedTests } from '../services/resultService';
import { getAvailableTests } from '../services/testService';

const router = useRouter();
const tests = ref([]);

// The testing page loads the questions for the chosen test from its ID in the URL.
function startTest(test) {
    router.push({ name: 'testing', params: { testID: test.testID } });
}
const isLoading = ref(true);
const loadError = ref('');

// 3 columns on desktop, 2 on tablets and 1 on phones, the same breakpoints as Bootstrap's lg and sm.
const tabletQuery = '(max-width: 991.98px)';
const phoneQuery = '(max-width: 575.98px)';
const columnCount = ref(3);
const mediaQueries = [];

function updateColumnCount() {
    if (typeof window.matchMedia !== 'function') {
        return;
    }
    if (window.matchMedia(phoneQuery).matches) {
        columnCount.value = 1;
    } else if (window.matchMedia(tabletQuery).matches) {
        columnCount.value = 2;
    } else {
        columnCount.value = 3;
    }
}

// Deal the tests out across the columns in turn, so they still read left to right in date order.
const testColumns = computed(() => {
    const columns = Array.from({ length: columnCount.value }, () => []);
    tests.value.forEach((test, index) => columns[index % columnCount.value].push(test));
    return columns.filter((column) => column.length > 0);
});

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
    updateColumnCount();
    if (typeof window.matchMedia === 'function') {
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
/* Each column is its own stack, so cards keep their own height and can't be split across columns. */
.testCardGrid {
    display: grid;
    grid-template-columns: repeat(var(--testCardColumns, 3), minmax(0, 1fr));
    align-items: start;
    gap: var(--spaceLg);
}

.testCardColumn {
    display: flex;
    flex-direction: column;
    gap: var(--spaceLg);
}
</style>
