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

            <div class="testCardActions mt-auto">
                <button type="button" class="btn testCardDetailsButton" @click="$emit('details', test)">Details</button>
                <button type="button" class="btn testCardStartButton" @click="$emit('start', test)">Start test</button>
            </div>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    test: {
        type: Object,
        required: true,
        validator: (test) =>
            typeof test.testName === 'string' &&
            typeof test.testDescription === 'string' &&
            typeof test.subjectName === 'string',
    },
});

defineEmits(['details', 'start']);

const hasTimeLimit = computed(() => Number.isInteger(props.test.timeLimit) && props.test.timeLimit > 0);
const questionCount = computed(() =>
    Number.isInteger(props.test.questionCount) ? props.test.questionCount : 'Not available',
);

const setBy = computed(() => props.test.createdByName || props.test.setBy || 'Not available');

const timeLimit = computed(() => {
    const minutes = props.test.timeLimit;
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    const parts = [];

    if (hours > 0) {
        parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
    }
    if (remainingMinutes > 0) {
        parts.push(`${remainingMinutes} ${remainingMinutes === 1 ? 'minute' : 'minutes'}`);
    }

    return parts.join(' ');
});

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

.testCardActions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spaceSm);
}

.testCardDetailsButton,
.testCardStartButton {
    min-height: var(--minTouchTarget);
}

.testCardDetailsButton {
    border-color: var(--colorDivider);
    background: var(--colorDisabled);
    color: var(--colorText);
}

.testCardDetailsButton:hover {
    border-color: var(--colorDivider);
    background: var(--colorDivider);
    color: var(--colorText);
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
