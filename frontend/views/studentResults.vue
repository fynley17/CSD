<script setup>
import { computed, onMounted, ref } from 'vue';
import CompletedTestItem from '../components/common/completedTestItem.vue';
import PointsLineChart from '../components/common/pointsLineChart.vue';
import { getCompletedTests, getResultsSummary } from '../services/resultService';

const resultsError = ref('');
const summary = ref(null);

// Shows a dash while loading or if the summary can't be loaded, so a made up 0 is never shown.
const summaryCards = computed(() => {
    if (summary.value === null) {
        return [
            { title: 'Total Points', value: '–' },
            { title: 'Average Score', value: '–' },
            { title: 'Tests Completed', value: '–' },
        ];
    }

    const { totalPoints, averageScore, testsCompleted } = summary.value;

    return [
        { title: 'Total Points', value: totalPoints },
        {
            title: 'Average Score',
            value: averageScore === null ? 'N/A' : `${averageScore}%`,
            subtitle:
                testsCompleted === 0
                    ? 'No tests completed yet'
                    : `Based on ${testsCompleted} ${testsCompleted === 1 ? 'test' : 'tests'}`,
        },
        { title: 'Tests Completed', value: testsCompleted },
    ];
});

const completedTests = ref([]);
const isLoadingCompleted = ref(true);
const completedLoadError = ref('');

// The list is newest first, the graph adds the points up from the oldest test.
const pointsHistory = computed(() => {
    let totalPoints = 0;

    return [...completedTests.value].reverse().map((result) => {
        totalPoints += result.pointsEarned;
        return { date: result.completedDate.slice(0, 10), testName: result.testName, totalPoints };
    });
});

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

async function loadSummary() {
    try {
        summary.value = await getResultsSummary();
    } catch (error) {
        resultsError.value =
            error.response?.data?.error || 'Unable to load your results summary. Please try again later.';
    }
}

// Load both side by side so a failure in one does not hide the other.
onMounted(() => Promise.all([loadSummary(), loadCompletedTests()]));
</script>

<template>
    <section class="container py-4 mb-0 pb-0">
        <div class="row mb-0">
            <div class="col">
                <h1>Results</h1>
                <p class="hintText mb-0">Here's an overview of your test performance.</p>
            </div>
            <div v-if="summary" class="col-auto">
                <p class="fw-bold mb-0 mt-3">Student: {{ summary.studentName }}</p>
                <p class="hintText mt-0 p-0">Student Number: {{ summary.studentNumber }}</p>
            </div>
        </div>
    </section>

    <!-- student information cards -->
    <section class="container py-4" aria-label="Student summary">
        <p v-if="resultsError" class="alert alert-danger mb-4" role="alert">
            {{ resultsError }}
        </p>

        <div class="row g-4">
            <div v-for="card in summaryCards" :key="card.title" class="col-12 col-md-4">
                <div class="card surfaceCard shadow-none h-100">
                    <div class="card-body">
                        <p class="hintText mb-1">{{ card.title }}</p>
                        <p class="statValue mb-1">{{ card.value }}</p>
                        <p v-if="card.subtitle" class="hintText small mb-0">{{ card.subtitle }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- points over time graph -->
    <section v-if="!isLoadingCompleted && !completedLoadError" class="container pb-4" aria-label="Points over time">
        <PointsLineChart :points="pointsHistory" />
    </section>

    <section class="container pb-4" aria-labelledby="completedTestsHeading">
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
</template>

<style scoped>
/* Stack completed tests in a single list beneath the cards. */
.completedTestList {
    display: grid;
    gap: var(--spaceMd);
}
</style>
