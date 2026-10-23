<template>
    <main class="container-fluid bg-light py-4 px-3 px-lg-4">
        <div class="container-fluid">
            <!-- Welcome section at the top of the lecturer dashboard. -->
            <section class="mb-4">
                <h1 class="h2 fw-bold mb-1">Lecturer Dashboard</h1>
                <p class="text-secondary mb-0">
                    Welcome, {{ lecturerName }}. Here's an overview of your platform activity.
                </p>
            </section>

            <!-- Display an error message if the dashboard data cannot be loaded. -->
            <p v-if="summaryError" class="alert alert-danger mb-4" role="alert">
                {{ summaryError }}
            </p>

            <!-- CTS-90: Display the four summary cards using data from the backend. -->
            <section class="row g-4 mb-4" aria-label="Dashboard summary">
                <!-- Create one card for each item in the summaryCards array. -->
                <div v-for="card in summaryCards" :key="card.title" class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 border-0 rounded-0">
                        <div class="card-body d-flex flex-column text-center">
                            <!-- Display the image associated with this summary card. -->
                            <div class="dashboardCardImage mb-3">
                                <img :src="card.image" :alt="card.title" class="dashboardCardImageContent" />
                            </div>

                            <!-- Display the card title, such as Total Students. -->
                            <h2 class="h6 fw-bold mb-3">
                                {{ card.title }}
                            </h2>

                            <!-- Display the count returned by the backend.
                                 Show an ellipsis while loading and a dash if no value is available. -->
                            <p class="display-6 fw-bold mt-auto mb-0" :aria-label="`${card.title}: ${card.value}`">
                                {{ card.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTS-91 and CTS-92: Display the student and recent test tables. -->
            <section class="row g-4">
                <div class="col-12 col-xl-6">
                    <StudentTable />
                </div>

                <div class="col-12 col-xl-6">
                    <TestTable />
                </div>
            </section>
        </div>
    </main>
</template>

<script setup>
// Import Vue functions for reactive data, computed values and component lifecycle.
import { computed, onMounted, ref } from 'vue';

// Import reusable components for the dashboard tables.
import StudentTable from '../components/tables/studentTable.vue';
import TestTable from '../components/tables/testTable.vue';

// Import the images used by the summary cards.
import studentsImage from '../assets/images/students.jpg';
import approvalsImage from '../assets/images/approvals.jpg';
import testsImage from '../assets/images/tests.jpg';
import lockedAccountsImage from '../assets/images/lockedAccounts.jpg';

// Import the service responsible for requesting summary data from the PHP backend.
import { getDashboardSummary } from '../services/lecturerService';

// Temporary lecturer name until the authenticated user's name is available.
const lecturerName = 'Paul';

// Store the summary returned by the backend.
// ref() makes the value reactive so Vue updates the dashboard when it changes.
const summary = ref(null);

// Track whether the dashboard is still waiting for the backend response.
const isLoading = ref(true);

// Store an error message if the summary request fails.
const summaryError = ref('');

// Build the card data from the backend response.
// computed() automatically updates the cards when the summary or loading state changes.
const summaryCards = computed(() => [
    {
        title: 'Total Students',
        value: isLoading.value ? '…' : (summary.value?.totalStudents ?? '—'),
        image: studentsImage,
    },
    {
        title: 'Pending Approvals',
        value: isLoading.value ? '…' : (summary.value?.pendingApprovals ?? '—'),
        image: approvalsImage,
    },
    {
        title: 'Tests Completed',
        value: isLoading.value ? '…' : (summary.value?.completedTestAttempts ?? '—'),
        image: testsImage,
    },
    {
        title: 'Disabled Accounts',
        value: isLoading.value ? '…' : (summary.value?.disabledAccounts ?? '—'),
        image: lockedAccountsImage,
    },
]);

// Run when the dashboard component first loads.
// Request the latest summary counts instead of hardcoding the numbers.
onMounted(async () => {
    try {
        // Retrieve the four counts through the lecturer service.
        summary.value = await getDashboardSummary();
    } catch (error) {
        // Use the error message returned by the backend when available.
        // Otherwise, display a general message to the lecturer.
        summaryError.value = error.response?.data?.error || 'Unable to load dashboard summary. Please try again.';
    } finally {
        // Stop showing the loading state whether the request succeeds or fails.
        isLoading.value = false;
    }
});
</script>
