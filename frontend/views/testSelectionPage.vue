<template>
    <main class="container py-4">
        <h1>Your tests to complete</h1>

        <p v-if="isLoading" class="text-secondary" role="status">Loading tests...</p>
        <p v-else-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="tests.length === 0" class="text-secondary">There are no tests available right now.</p>

        <div v-else class="testCardGrid mt-4">
            <div v-for="test in tests" :key="test.testID" class="testCardGridItem">
                <TestCard :test="test" />
            </div>
        </div>
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import TestCard from '../components/common/testCard.vue';
import { getAvailableTests } from '../services/testService';

const tests = ref([]);
const isLoading = ref(true);
const loadError = ref('');

onMounted(async () => {
    try {
        tests.value = await getAvailableTests();
    } catch (error) {
        loadError.value = error.response?.data?.error || 'Unable to load tests. Please try again later.';
    } finally {
        isLoading.value = false;
    }
});
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
