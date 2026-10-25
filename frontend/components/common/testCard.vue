<template>
    <article class="card testCard">
        <div class="card-body d-flex flex-column p-4">
            <h2 class="testCardTitle mb-2">{{ test.testName }}</h2>

            <p class="testCardDescription text-secondary mb-3">
                {{ test.testDescription }}
            </p>

            <dl class="testCardDetails mb-4">
                <div v-if="hasTimeLimit">
                    <dt>Time limit</dt>
                    <dd>{{ timeLimit }}</dd>
                </div>
                <div>
                    <dt>Questions</dt>
                    <dd>{{ questionCount }}</dd>
                </div>
                <div>
                    <dt>Subject</dt>
                    <dd>{{ test.subjectName }}</dd>
                </div>
                <div>
                    <dt>Set by</dt>
                    <dd>{{ setBy }}</dd>
                </div>
                <div v-if="dueDate">
                    <dt>Due by</dt>
                    <dd>{{ dueDate }}</dd>
                </div>
            </dl>

            <button type="button" class="btn testCardStartButton" @click="$emit('start', test)">Start Test</button>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { formatTimeLimit } from '../../utils/formatTimeLimit';

// The card shows the key information a student needs to decide whether to
const props = defineProps({
    // Keep the prop contract strict so the component gets the expected data
    // shape.
    test: {
        type: Object,
        required: true,
        validator: (test) =>
            typeof test.testName === 'string' &&
            typeof test.testDescription === 'string' &&
            typeof test.subjectName === 'string',
    },
});

defineEmits(['start']);

// Only show the time limit when it is a positive value.
const hasTimeLimit = computed(() => Number.isInteger(props.test.timeLimit) && props.test.timeLimit > 0);

// Show the question count when available, otherwise use a fallback label.
const questionCount = computed(() =>
    Number.isInteger(props.test.questionCount) ? props.test.questionCount : 'Not available',
);

// Support older payloads by falling back to a second field when needed.
const setBy = computed(() => props.test.createdByName || props.test.setBy || 'Not available');

// Convert minutes into a readable string such as "1 hour 30 minutes".
const timeLimit = computed(() => formatTimeLimit(props.test.timeLimit));

// Format the date for display when it is valid; otherwise hide it.
const dueDate = computed(() => {
    if (!props.test.dueDate) {
        return '';
    }

    const date = new Date(props.test.dueDate);
    if (Number.isNaN(date.getTime())) {
        return props.test.dueDate;
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
});
</script>

<style scoped>
/* Keep the card compact enough to sit in a grid of student tests. */
.testCard {
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusMd);
    box-shadow: var(--shadowCard);
}

.testCardTitle {
    color: var(--colorText);
    font-size: var(--fontSizeTestCardTitle);
    font-weight: 700;
}

/* Display the key details in a simple summary layout. */
.testCardDetails {
    display: grid;
    gap: var(--spaceSm);
}

.testCardDetails div {
    display: flex;
    justify-content: space-between;
    gap: var(--spaceMd);
}

.testCardDetails dt {
    color: var(--colorTextSecondary);
    font-weight: 400;
}

.testCardDetails dd {
    margin: 0;
    font-weight: 600;
    text-align: right;
}

/* Keep the actions side by side with a comfortable touch target. */
.testCardActions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spaceSm);
}

.testCardStartButton {
    min-height: var(--minTouchTarget);
}

.testCardStartButton {
    border-color: var(--colorBrand);
    background: var(--colorBrand);
    color: var(--colorSurface);
}

.testCardStartButton:hover {
    border-color: var(--colorPrimaryHover);
    background: var(--colorPrimaryHover);
    color: var(--colorSurface);
}
</style>
