<script setup>
import { computed, ref } from 'vue';
import { Chart, LinearScale, LineElement, PointElement, TimeScale, Tooltip } from 'chart.js';
import 'chartjs-adapter-date-fns';
import { Line } from 'vue-chartjs';

Chart.register(LinearScale, LineElement, PointElement, TimeScale, Tooltip);

const props = defineProps({
    // oldest first, each one is { date: 'YYYY-MM-DD HH:MM:SS', testName, totalPoints }
    points: {
        type: Array,
        required: true,
    },
});

// calendar dates rather than a number of hours, so the clocks changing can't move the start of a period
const daysBefore = (date, days) => new Date(date).setDate(date.getDate() - days);
const monthsBefore = (date, months) => new Date(date).setMonth(date.getMonth() - months);

// startOf is left out for All time, so the graph uses every test
const timeRanges = [
    { id: 'week', label: 'Week', emptyText: 'week', startOf: (now) => daysBefore(now, 7) },
    { id: 'month', label: 'Month', emptyText: 'month', startOf: (now) => monthsBefore(now, 1) },
    { id: 'threeMonths', label: '3 months', emptyText: '3 months', startOf: (now) => monthsBefore(now, 3) },
    { id: 'year', label: 'Year', emptyText: 'year', startOf: (now) => monthsBefore(now, 12) },
    { id: 'allTime', label: 'All time' },
];

const selectedRange = ref(timeRanges[timeRanges.length - 1]);

// Chart.js draws on a canvas, which can't read CSS variables, so the design tokens are looked up here
const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const dateFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

// the database sends a space between the date and time, new Date() reads it as local time with a T
const toTime = (date) => new Date(date.replace(' ', 'T')).getTime();

const visibleRange = computed(() => {
    const now = new Date();
    return selectedRange.value.startOf ? { min: selectedRange.value.startOf(now), max: now.getTime() } : {};
});

// totalPoints is worked out before this filter, so each dot keeps the student's real running total
const visiblePoints = computed(() => {
    const { min } = visibleRange.value;
    return props.points.filter((point) => min === undefined || toTime(point.date) >= min);
});

const chartData = computed(() => ({
    datasets: [
        {
            data: visiblePoints.value.map((point) => ({ x: toTime(point.date), y: point.totalPoints })),
            borderColor: token('--colorPrimary'),
            backgroundColor: token('--colorPrimary'),
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    // the tooltip snaps to the nearest test, so the pointer doesn't have to land on the line
    interaction: { mode: 'nearest', axis: 'x', intersect: false },
    plugins: {
        tooltip: {
            displayColors: false,
            callbacks: {
                title: (items) => visiblePoints.value[items[0].dataIndex].testName,
                label: (item) => `${item.parsed.y} points`,
                afterLabel: (item) => dateFormat.format(toTime(visiblePoints.value[item.dataIndex].date)),
            },
        },
    },
    scales: {
        x: {
            type: 'time',
            ...visibleRange.value,
            time: {
                displayFormats: { hour: 'HH:mm', day: 'd MMM', week: 'd MMM', month: 'MMM yyyy', quarter: 'MMM yyyy' },
            },
            grid: { display: false },
            ticks: { color: token('--colorTextSecondary'), autoSkipPadding: 16, maxRotation: 0 },
        },
        y: {
            // a shorter period follows the line, otherwise a small rise on a big total would look flat
            beginAtZero: !selectedRange.value.startOf,
            grid: { color: token('--colorDisabled') },
            ticks: { color: token('--colorTextSecondary') },
        },
    },
}));

const chartSummary = computed(() => {
    const latest = visiblePoints.value[visiblePoints.value.length - 1];
    const testCount = visiblePoints.value.length;
    return (
        `Line chart of total points over ${testCount} ${testCount === 1 ? 'test' : 'tests'}, ` +
        `reaching ${latest.totalPoints} points.`
    );
});
</script>

<template>
    <div class="card surfaceCard shadow-none">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="mb-1">Points over time</h2>
                    <p class="hintText mb-0">Your total points after each completed test.</p>
                </div>

                <div v-if="points.length > 0" class="btn-group btn-group-sm" role="group" aria-label="Time period">
                    <button
                        v-for="range in timeRanges"
                        :key="range.id"
                        type="button"
                        class="btn btn-outline-secondary timeRangeButton"
                        :class="{ active: selectedRange.id === range.id }"
                        :aria-pressed="selectedRange.id === range.id"
                        @click="selectedRange = range"
                    >
                        {{ range.label }}
                    </button>
                </div>
            </div>

            <p v-if="points.length === 0" class="hintText mb-0">Complete a test to see your points here.</p>
            <p v-else-if="visiblePoints.length === 0" class="hintText mb-0" role="status">
                No tests in the last {{ selectedRange.emptyText }}.
            </p>

            <!-- Chart.js sizes itself to this box, so the height is set here -->
            <div v-else class="pointsChart">
                <Line :data="chartData" :options="chartOptions" :aria-label="chartSummary" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.pointsChart {
    position: relative;
    height: 280px;
}

/* big enough to tap on a phone */
.timeRangeButton {
    display: flex;
    min-height: var(--minTouchTarget);
    align-items: center;
    justify-content: center;
}
</style>
