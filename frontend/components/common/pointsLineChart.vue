<script setup>
import { computed } from 'vue';
import { CategoryScale, Chart, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
import { Line } from 'vue-chartjs';

Chart.register(CategoryScale, LinearScale, LineElement, PointElement, Tooltip);

const props = defineProps({
    // oldest first, each one is { date: 'YYYY-MM-DD', testName, totalPoints }
    points: {
        type: Array,
        required: true,
    },
});

// Chart.js draws on a canvas, which can't read CSS variables, so the design tokens are looked up here
const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const dateFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' });

const chartData = computed(() => ({
    labels: props.points.map((point) => dateFormat.format(new Date(`${point.date}T00:00:00`))),
    datasets: [
        {
            data: props.points.map((point) => point.totalPoints),
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
    interaction: { mode: 'index', intersect: false },
    plugins: {
        tooltip: {
            displayColors: false,
            callbacks: {
                title: (items) => props.points[items[0].dataIndex].testName,
                label: (item) => `${item.parsed.y} points`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { color: token('--colorTextSecondary'), autoSkipPadding: 16, maxRotation: 0 },
        },
        y: {
            beginAtZero: true,
            grid: { color: token('--colorDisabled') },
            ticks: { color: token('--colorTextSecondary') },
        },
    },
}));

const chartSummary = computed(() => {
    const latest = props.points[props.points.length - 1];
    return `Line chart of total points over ${props.points.length} tests, currently ${latest.totalPoints} points.`;
});
</script>

<template>
    <div class="card surfaceCard shadow-none">
        <div class="card-body">
            <h2 class="mb-1">Points over time</h2>
            <p class="hintText">Your total points after each completed test.</p>

            <p v-if="points.length === 0" class="hintText mb-0">Complete a test to see your points here.</p>

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
</style>
