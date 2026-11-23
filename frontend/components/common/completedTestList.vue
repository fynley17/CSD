<template>
    <div class="mt-4">
        <ul class="completedTestList list-unstyled mb-0">
            <li v-for="result in visibleTests" :key="result.completedID">
                <CompletedTestItem :result="result" />
            </li>
        </ul>

        <ListPagination
            v-model:page="currentPage"
            :pageSize="pageSize"
            :total="tests.length"
            label="Completed tests pages"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import CompletedTestItem from './completedTestItem.vue';
import ListPagination from './listPagination.vue';

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
const currentPage = computed({
    get: () => Math.min(page.value, Math.max(pageCount.value, 1)),
    set: (value) => {
        page.value = value;
    },
});

const visibleTests = computed(() =>
    props.tests.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize),
);
</script>

<style scoped>
/* Stack completed tests in a single list beneath the cards. */
.completedTestList {
    display: grid;
    gap: var(--spaceMd);
}
</style>
