<script setup>
// import studentPointsTable from '@/components/studentPointsTablevue';

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
</template>
