<template>
    <main class="container py-4">
        <h1>Your tests to complete</h1>

        <p v-if="isLoading" class="text-secondary" role="status">Loading tests...</p>
        <p v-else-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="tests.length === 0" class="text-secondary">There are no tests available right now.</p>

        <div v-else class="testCardGrid mt-4">
            <div v-for="test in tests" :key="test.testID" class="testCardGridItem">
                <TestCard :test="test" @start="startTest" />
            </div>
        </div>

        <section class="mt-5" aria-labelledby="completedTestsHeading">
            <h2 id="completedTestsHeading">Completed tests</h2>

            <p v-if="isLoadingCompleted" class="text-secondary" role="status">Loading completed tests...</p>
            <p v-else-if="completedLoadError" class="alert alert-danger" role="alert">{{ completedLoadError }}</p>
            <p v-else-if="completedTests.length === 0" class="text-secondary">You have not completed any tests yet.</p>

            <ul v-else class="completedTestList list-unstyled mt-4 mb-0">
                <li v-for="result in completedTests" :key="result.completedID">
                    <CompletedTestItem :result="result" />
                </li>
            </ul>
        </section>
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import CompletedTestItem from '../components/common/completedTestItem.vue';
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

// Load both lists side by side so a failure in one does not hide the other.
onMounted(() => Promise.all([loadAvailableTests(), loadCompletedTests()]));
</script>

<style scoped>
.testCardGrid {
    columns: 3;
    column-gap: var(--spaceLg);
}

.testCardGridItem {
    break-inside: avoid;
    margin-bottom: var(--spaceLg);
}

/* Stack completed tests in a single list beneath the cards. */
.completedTestList {
    display: grid;
    gap: var(--spaceMd);
}

@media (max-width: 991.98px) {
    .testCardGrid {
        columns: 2;
    }
}

@media (max-width: 575.98px) {
    .testCardGrid {
        columns: 1;
    }
}
</style>
