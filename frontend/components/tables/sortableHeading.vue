<!-- A column heading that sorts its table when clicked, with up and down arrows like the DataTables tables -->
<template>
    <th scope="col" :aria-sort="ariaSort" class="sortableHeading">
        <button type="button" class="sortableHeadingButton" @click="emit('sort', column)">
            <span><slot></slot></span>
            <span class="sortArrows" :class="arrowClass" aria-hidden="true"></span>
        </button>
    </th>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    // the key for this column in the table's sort values
    column: {
        type: String,
        required: true,
    },
    // the table's current sort, i.e. { key: 'name', direction: 'asc' }
    sort: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['sort']);

const direction = computed(() => (props.sort.key === props.column ? props.sort.direction : ''));

// screen readers say which column is sorted and which way, the arrows are only for sighted users
const ariaSort = computed(() => ({ asc: 'ascending', desc: 'descending' })[direction.value] || null);

const arrowClass = computed(() => ({ asc: 'sortArrowsAsc', desc: 'sortArrowsDesc' })[direction.value] || '');
</script>
