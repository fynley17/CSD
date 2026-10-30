<script setup>
// import studentPointsTable from '@/components/studentPointsTablevue';
import PointsLineChart from '../components/common/pointsLineChart.vue';

const studentName = 'John Doe';
const studentNumber = 'WS381211';
const resultsError = '';
const testsCompleted = 10;
const summaryCards = [
    {
        title: 'Total Points',
        value: 85,
    },
    {
        title: 'Average Score',
        value: '85%',
        subtitle: 'Based on ' + testsCompleted + ' tests',
    },
    {
        title: 'Tests Completed',
        value: testsCompleted,
    },
];
// placeholder data until the results API is connected, the last total matches the Total Points card
const pointsHistory = [
    { date: '2026-09-02', testName: 'Test 1', totalPoints: 8 },
    { date: '2026-09-09', testName: 'Test 2', totalPoints: 15 },
    { date: '2026-09-14', testName: 'Test 3', totalPoints: 24 },
    { date: '2026-09-23', testName: 'Test 4', totalPoints: 30 },
    { date: '2026-09-30', testName: 'Test 5', totalPoints: 39 },
    { date: '2026-10-05', testName: 'Test 6', totalPoints: 47 },
    { date: '2026-10-12', testName: 'Test 7', totalPoints: 55 },
    { date: '2026-10-16', testName: 'Test 8', totalPoints: 62 },
    { date: '2026-10-23', testName: 'Test 9', totalPoints: 76 },
    { date: '2026-10-28', testName: 'Test 10', totalPoints: 85 },
];
</script>

<template>
    <section class="container py-4 mb-0 pb-0">
        <div class="row mb-0">
            <div class="col">
                <h1>Results</h1>
                <p class="hintText mb-0">Here's an overview of your test performance.</p>
            </div>
            <div class="col-auto">
                <p class="fw-bold mb-0 mt-3">Student: {{ studentName }}</p>
                <p class="hintText mt-0 p-0">Student Number: {{ studentNumber }}</p>
            </div>
        </div>
    </section>
    <p v-if="resultsError" class="alert alert-danger mb-4" role="alert">
        {{ resultsError }}
    </p>

    <!-- student information cards -->
    <section class="container py-4" aria-label="Student summary">
        <div class="row g-4">
            <div v-for="card in summaryCards" :key="card.title" class="col-12 col-md-4">
                <div class="card surfaceCard shadow-none h-100">
                    <div class="card-body">
                        <p class="hintText mb-1">{{ card.title }}</p>
                        <p class="statValue mb-1">{{ card.value }}</p>
                        <p v-if="card.subtitle" class="hintText small mb-0">{{ card.subtitle }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- points over time graph -->
    <section class="container pb-4" aria-label="Points over time">
        <PointsLineChart :points="pointsHistory" />
    </section>
</template>
