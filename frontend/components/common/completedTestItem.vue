<template>
    <article class="card completedTestItem">
        <div class="card-body d-flex align-items-center gap-3 p-4">
            <div class="completedTestInfo flex-grow-1">
                <h3 class="completedTestTitle mb-1">{{ result.testName }}</h3>

                <p class="completedTestDescription text-secondary mb-2">
                    {{ result.testDescription }}
                </p>

                <dl class="completedTestDetails mb-0">
                    <div>
                        <dt>Subject</dt>
                        <dd>{{ result.subjectName }}</dd>
                    </div>
                    <div>
                        <dt>Completed</dt>
                        <dd>{{ completedDate }}</dd>
                    </div>
                    <div>
                        <dt>Questions</dt>
                        <dd>{{ result.questionCount }}</dd>
                    </div>
                    <div>
                        <dt>Points scored</dt>
                        <dd>{{ result.pointsEarned }}</dd>
                    </div>
                </dl>
            </div>

            <p class="completedTestPercentage mb-0">{{ percentage }}</p>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';

// The item summarises one finished test, with the score shown on the right so
// students can scan their results quickly.
const props = defineProps({
    // Keep the prop contract strict so the component gets the expected data
    // shape.
    result: {
        type: Object,
        required: true,
        validator: (result) =>
            typeof result.testName === 'string' &&
            typeof result.testDescription === 'string' &&
            typeof result.subjectName === 'string',
    },
});

// Show whole percentages without a decimal place, otherwise round to one.
const percentage = computed(() => {
    const value = Number(props.result.percentageCorrect);
    if (!Number.isFinite(value)) {
        return 'N/A';
    }

    return `${Number.isInteger(value) ? value : value.toFixed(1)}%`;
});

// Show the day the test was completed; fall back to the raw value if it
// cannot be parsed.
const completedDate = computed(() => {
    const date = new Date(props.result.completedDate);
    if (Number.isNaN(date.getTime())) {
        return props.result.completedDate;
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
});
</script>

<style scoped>
/* Match the test card styling so both lists feel like one page. */
.completedTestItem {
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusMd);
    box-shadow: var(--shadowCard);
}

.completedTestTitle {
    color: var(--colorText);
    font-size: var(--fontSizeTestCardTitle);
    font-weight: 700;
}

/* Lay the secondary details out inline so each item stays compact. */
.completedTestDetails {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spaceXs) var(--spaceLg);
    color: var(--colorTextSecondary);
}

.completedTestDetails div {
    display: flex;
    gap: var(--spaceXs);
}

.completedTestDetails dt {
    font-weight: 400;
}

.completedTestDetails dt::after {
    content: ':';
}

.completedTestDetails dd {
    margin: 0;
    font-weight: 400;
}

.completedTestPercentage {
    flex-shrink: 0;
    color: var(--colorText);
    font-size: var(--fontSizeStat);
    font-weight: 700;
}
</style>
