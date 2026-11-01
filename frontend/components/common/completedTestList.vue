<template>
    <div class="mt-4">
        <ul class="completedTestList list-unstyled mb-0">
            <li v-for="result in visibleTests" :key="result.completedID">
                <CompletedTestItem :result="result" />
            </li>
        </ul>

        <nav v-if="pageCount > 1" class="mt-3" aria-label="Completed tests pages">
            <p class="hintText small mb-2" role="status">
                Showing {{ firstShown }} to {{ lastShown }} of {{ tests.length }} tests
            </p>

            <ul class="pagination completedTestPagination flex-wrap mb-0">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button
                        type="button"
                        class="page-link"
                        :disabled="currentPage === 1"
                        @click="page = currentPage - 1"
                    >
                        Previous
                    </button>
                </li>
                <li
                    v-for="pageNumber in pageCount"
                    :key="pageNumber"
                    class="page-item"
                    :class="{ active: pageNumber === currentPage }"
                >
                    <button
                        type="button"
                        class="page-link"
                        :aria-label="`Page ${pageNumber}`"
                        :aria-current="pageNumber === currentPage ? 'page' : undefined"
                        @click="page = pageNumber"
                    >
                        {{ pageNumber }}
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === pageCount }">
                    <button
                        type="button"
                        class="page-link"
                        :disabled="currentPage === pageCount"
                        @click="page = currentPage + 1"
                    >
                        Next
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import CompletedTestItem from './completedTestItem.vue';

const pageSize = 5;

const props = defineProps({
    // newest first, the order the API sends them in
    tests: {
        type: Array,
        required: true,
    },
});

const page = ref(1);

const pageCount = computed(() => Math.ceil(props.tests.length / pageSize));

// stays on a real page if the list gets shorter
const currentPage = computed(() => Math.min(page.value, Math.max(pageCount.value, 1)));

const firstShown = computed(() => (currentPage.value - 1) * pageSize + 1);
const lastShown = computed(() => Math.min(currentPage.value * pageSize, props.tests.length));

const visibleTests = computed(() => props.tests.slice(firstShown.value - 1, lastShown.value));
</script>

<style scoped>
/* Stack completed tests in a single list beneath the cards. */
.completedTestList {
    display: grid;
    gap: var(--spaceMd);
}

/* Bootstrap's pagination in the house colours, with buttons big enough to tap on a phone. */
.completedTestPagination {
    --bs-pagination-color: var(--colorPrimary);
    --bs-pagination-hover-color: var(--colorPrimaryHover);
    --bs-pagination-active-bg: var(--colorPrimary);
    --bs-pagination-active-border-color: var(--colorPrimary);
    --bs-pagination-disabled-color: var(--colorTextSecondary);
}

.completedTestPagination button {
    min-width: var(--minTouchTarget);
    min-height: var(--minTouchTarget);
}
</style>
