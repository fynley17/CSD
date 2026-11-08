<!-- A labelled dropdown that filters a table, every table uses this so the filters all look the same -->
<template>
    <div class="tableFilter">
        <label :for="id" class="form-label tableFilterLabel">{{ label }}</label>
        <select :id="id" v-model="selected" class="form-select tableFilterSelect" @change="emit('change', selected)">
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </div>
</template>

<script setup>
// the picked value, the first option is always the "All ..." one
const selected = defineModel({ type: String, default: '' });

defineProps({
    // unique on the page so the label is tied to its dropdown
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    // i.e. [{ value: '', label: 'All statuses' }, { value: 'active', label: 'Active' }]
    options: {
        type: Array,
        required: true,
    },
});

// fires after the value has changed, for pages that filter the table with a method call
const emit = defineEmits(['change']);
</script>
