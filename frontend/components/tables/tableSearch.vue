<!-- Search box above a table, sits in the toolbar next to the filter dropdowns and looks like them -->
<template>
    <div class="tableSearch">
        <label :for="id" class="form-label tableFilterLabel">{{ label }}</label>
        <div class="tableSearchField">
            <i class="bi bi-search tableSearchIcon" aria-hidden="true"></i>
            <input
                :id="id"
                v-model="text"
                type="search"
                class="form-control tableSearchInput"
                :placeholder="placeholder"
            />
        </div>
    </div>
</template>

<script setup>
const text = defineModel({ type: String, default: '' });

defineProps({
    // unique on the page so the label is tied to its box
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: 'Search',
    },
    // a hint at what can be searched, i.e. Name, email or staff ID. The label is what screen readers use
    placeholder: {
        type: String,
        default: '',
    },
});
</script>
