<!-- One row per cohort that opens to show more detail, with paging. The two cohort tables fill in the columns -->
<template>
    <!-- same Bootstrap table classes as the DataTables tables, so every table on the site looks alike -->
    <div class="adminTable cohortTableScroller" role="region" tabindex="0" :aria-label="label">
        <table class="table table-sm table-hover align-middle mb-0 cohortTable">
            <caption class="visually-hidden">
                {{
                    label
                }}
            </caption>
            <thead>
                <tr>
                    <th scope="col"><span class="visually-hidden">Show details</span></th>
                    <slot name="headings"></slot>
                </tr>
            </thead>
            <tbody>
                <tr v-if="cohorts.length === 0">
                    <td :colspan="columnCount" class="cohortTableEmpty">No cohorts match your search</td>
                </tr>
                <template v-for="cohort in pageOfCohorts" :key="cohort.cohortID">
                    <tr>
                        <td class="cohortToggleCell">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary cohortToggle"
                                :aria-expanded="isOpen(cohort)"
                                :aria-controls="isOpen(cohort) ? detailsID(cohort) : null"
                                :aria-label="`Show details for ${cohort.cohortName}`"
                                @click="toggle(cohort)"
                            >
                                <i
                                    class="bi"
                                    :class="isOpen(cohort) ? 'bi-chevron-down' : 'bi-chevron-right'"
                                    aria-hidden="true"
                                ></i>
                            </button>
                        </td>
                        <th scope="row" class="cohortNameCell">{{ cohort.cohortName }}</th>
                        <slot name="cells" :cohort="cohort"></slot>
                    </tr>
                    <tr v-if="isOpen(cohort)" :id="detailsID(cohort)" class="cohortDetailsRow">
                        <td :colspan="columnCount">
                            <slot name="details" :cohort="cohort"></slot>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- laid out like the DataTables footer, count on the left and Bootstrap page buttons on the right -->
    <div class="cohortPaging">
        <!-- role status so screen readers hear the new total after searching or changing page -->
        <p class="cohortPagingSummary" role="status">{{ pagingSummary }}</p>

        <nav :aria-label="`${label} pages`">
            <ul class="pagination mb-0">
                <li class="page-item" :class="{ disabled: page === 1 }">
                    <button
                        type="button"
                        class="page-link"
                        aria-label="Previous page"
                        :disabled="page === 1"
                        @click="page -= 1"
                    >
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </button>
                </li>
                <li v-for="number in pageCount" :key="number" class="page-item" :class="{ active: number === page }">
                    <button
                        type="button"
                        class="page-link"
                        :aria-label="`Page ${number}`"
                        :aria-current="number === page ? 'page' : null"
                        @click="page = number"
                    >
                        {{ number }}
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: page === pageCount }">
                    <button
                        type="button"
                        class="page-link"
                        aria-label="Next page"
                        :disabled="page === pageCount"
                        @click="page += 1"
                    >
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const PAGE_SIZE = 4;

const props = defineProps({
    cohorts: {
        type: Array,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    // every column in the table, including the toggle and cohort name
    columnCount: {
        type: Number,
        required: true,
    },
});

const page = ref(1);
// every cohort starts closed, the details only show once a row is opened
const openCohortIDs = ref(new Set());

const pageCount = computed(() => Math.max(1, Math.ceil(props.cohorts.length / PAGE_SIZE)));
const firstIndex = computed(() => (page.value - 1) * PAGE_SIZE);
const pageOfCohorts = computed(() => props.cohorts.slice(firstIndex.value, firstIndex.value + PAGE_SIZE));

const pagingSummary = computed(() => {
    const total = props.cohorts.length;
    if (total === 0) {
        return 'Showing 0 cohorts';
    }

    return `Showing ${firstIndex.value + 1}-${firstIndex.value + pageOfCohorts.value.length} of ${total} cohorts`;
});

// a new search or sort changes the list, so go back to the start of it
watch(
    () => props.cohorts,
    () => {
        page.value = 1;
    },
);

function isOpen(cohort) {
    return openCohortIDs.value.has(cohort.cohortID);
}

function toggle(cohort) {
    if (isOpen(cohort)) {
        openCohortIDs.value.delete(cohort.cohortID);
    } else {
        openCohortIDs.value.add(cohort.cohortID);
    }
}

// label is included as both tables on the page list the same cohorts, and ids have to be unique
function detailsID(cohort) {
    return `${props.label.replace(/\s+/g, '')}${cohort.cohortID}`;
}
</script>
