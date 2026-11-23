<template>
    <!-- only needed once there's more than one page -->
    <nav v-if="pageCount > 1" class="mt-3" :aria-label="label">
        <p class="hintText small mb-2" role="status">
            Showing {{ firstShown }} to {{ lastShown }} of {{ total }} {{ itemName }}
        </p>

        <ul class="pagination listPagination flex-wrap mb-0">
            <li class="page-item" :class="{ disabled: page === 1 }">
                <button type="button" class="page-link" :disabled="page === 1" @click="goTo(page - 1)">Previous</button>
            </li>
            <li
                v-for="pageNumber in pageCount"
                :key="pageNumber"
                class="page-item"
                :class="{ active: pageNumber === page }"
            >
                <button
                    type="button"
                    class="page-link"
                    :aria-label="`Page ${pageNumber}`"
                    :aria-current="pageNumber === page ? 'page' : undefined"
                    @click="goTo(pageNumber)"
                >
                    {{ pageNumber }}
                </button>
            </li>
            <li class="page-item" :class="{ disabled: page === pageCount }">
                <button type="button" class="page-link" :disabled="page === pageCount" @click="goTo(page + 1)">
                    Next
                </button>
            </li>
        </ul>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

// Previous, numbered pages and Next under a list, shared by the tests to complete and completed tests lists.
// The parent keeps the page number (v-model) and decides how many items fit on a page.
const props = defineProps({
    // the page being shown, starting at 1
    page: { type: Number, required: true },
    pageSize: { type: Number, required: true },
    total: { type: Number, required: true },
    // read out by screen readers, i.e. "Completed tests pages"
    label: { type: String, required: true },
    // the word after the count, i.e. "Showing 1 to 5 of 12 tests"
    itemName: { type: String, default: 'tests' },
});

const emit = defineEmits(['update:page']);

const pageCount = computed(() => Math.ceil(props.total / props.pageSize));
const firstShown = computed(() => (props.page - 1) * props.pageSize + 1);
const lastShown = computed(() => Math.min(props.page * props.pageSize, props.total));

function goTo(pageNumber) {
    emit('update:page', pageNumber);
}
</script>

<style scoped>
/* Bootstrap's pagination in the house colours, with buttons big enough to tap on a phone. */
.listPagination {
    --bs-pagination-color: var(--colorPrimary);
    --bs-pagination-hover-color: var(--colorPrimaryHover);
    --bs-pagination-active-bg: var(--colorPrimary);
    --bs-pagination-active-border-color: var(--colorPrimary);
    --bs-pagination-disabled-color: var(--colorTextSecondary);
}

.listPagination button {
    min-width: var(--minTouchTarget);
    min-height: var(--minTouchTarget);
}
</style>
