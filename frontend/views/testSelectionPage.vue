<template>
    <main class="container py-4">
        <h1>Your tests to complete</h1>

        <p v-if="isLoading" class="hintText" role="status">Loading tests...</p>
        <p v-else-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="tests.length === 0" class="hintText">There are no tests available right now.</p>

        <!-- every card is the same size, the tallest card sets the height of every row -->
        <div v-else class="testCardGrid mt-4">
            <TestCard v-for="test in tests" :key="test.testID" :test="test" @start="startTest" />
        </div>

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
import { onMounted, ref } from 'vue';
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
