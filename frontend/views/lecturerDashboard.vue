<template>
    <main class="container-fluid bg-light py-4 px-3 px-lg-4">
        <div class="container-fluid">
            <!-- Dashboard welcome -->
            <section class="mb-4">
                <h1 class="h2 fw-bold mb-1">Lecturer Dashboard</h1>
                <p class="text-secondary mb-0">
                    Welcome, {{ lecturerName }}. Here's an overview of your platform activity.
                </p>
            </section>

            <!-- To do CTS-90 -->
            <!-- Summary cards -->
            <section class="row g-4 mb-4" aria-label="Dashboard summary">
                <div v-for="card in summaryCards" :key="card.title" class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 border-0 rounded-0">
                        <div class="card-body d-flex flex-column text-center">
                            <div class="dashboardCardImage mb-3">
                                <img :src="card.image" :alt="card.title" class="dashboardCardImageContent" />
                            </div>

                            <h2 class="h6 fw-bold mb-3">
                                {{ card.title }}
                            </h2>

                            <p class="display-6 fw-bold mt-auto mb-0">
                                {{ card.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- To do CTS-91 and CTS-92 -->
            <!-- Dashboard tables -->
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
import StudentTable from '../components/tables/studentTable.vue';
import TestTable from '../components/tables/testTable.vue';

import studentsImage from '../assets/images/students.jpg';
import approvalsImage from '../assets/images/approvals.jpg';
import testImage from '../assets/images/tests.jpg';
import lockedAccountsImage from '../assets/images/lockedAccounts.jpg';

const summaryCards = [
    {
        title: 'Total Students',
        value: '—',
        image: studentsImage,
    },
    {
        title: 'Pending Approvals',
        value: '—',
        image: approvalsImage,
    },
    {
        title: 'Active Tests',
        value: '—',
        image: testImage,
    },
    {
        title: 'Disabled Accounts',
        value: '—',
        image: lockedAccountsImage,
    },
];

// TODO: Replace with the authenticated lecturer's name once login is done
const lecturerName = 'Paul';
</script>
