<!-- Administrator dashboard, where admins land after logging in, four cards give the totals at a glance -->
<template>
    <section class="container-fluid py-4 px-3 px-lg-4">
        <div class="container-fluid">
            <section class="mb-4">
                <h1 class="h2 fw-bold mb-1">Administrator Dashboard</h1>
                <p class="pageIntro mb-0">Here's an overview of subjects and lecturers.</p>
            </section>

            <!-- only shows if PHP couldn't send the totals, the cards then show a dash -->
            <div v-if="summaryError" class="alert alert-danger" role="alert">
                <strong>There is a problem:</strong> {{ summaryError }}
            </div>

            <!-- same card layout as the lecturer dashboard, so both dashboards look like one site -->
            <section class="row g-4 mb-4" aria-label="Dashboard summary">
                <div v-for="card in summaryCards" :key="card.title" class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 border-0 rounded-0">
                        <div class="card-body d-flex flex-column text-center">
                            <div class="dashboardCardImage mb-3">
                                <!-- empty alt as the picture is only decoration, the title says what the card is -->
                                <img :src="card.image" alt="" class="dashboardCardImageContent" />
                            </div>

                            <h2 class="h6 fw-bold mb-3">{{ card.title }}</h2>

                            <p class="display-6 fw-bold mt-auto mb-0">{{ card.value }}</p>
                        </div>
                    </div>
                </div>
            </section>
            <!-- lecturer and subject tables, side by side on big screens and stacked on phones -->
            <section class="row g-4">
                <div class="col-12 col-xl-6">
                    <LecturerTable />
                </div>
                <div class="col-12 col-xl-6">
                    <SubjectTable />
                </div>
            </section>
        </div>
    </section>
</template>

<script>
import { getDashboardSummary } from '../services/administratorService';
import subjectsImage from '../assets/images/tests.jpg';
import lecturersImage from '../assets/images/students.jpg';
import archivedImage from '../assets/images/approvals.jpg';
import disabledImage from '../assets/images/lockedAccounts.jpg';
import LecturerTable from '../components/tables/lecturerTable.vue';
import SubjectTable from '../components/tables/subjectTable.vue';

export default {
    components: {
        LecturerTable,
        SubjectTable,
    },
    data() {
        return {
            // the totals from PHP, null until they arrive
            summary: null,
            isLoading: true,
            summaryError: '',
        };
    },

    computed: {
        /**
         * The four cards, each with its title, number and picture
         * @returns the card list, the numbers show ... while loading and a dash if they couldn't be loaded
         */
        summaryCards() {
            return [
                { title: 'Total Subjects', value: this.cardValue('totalSubjects'), image: subjectsImage },
                { title: 'Total Lecturers', value: this.cardValue('totalLecturers'), image: lecturersImage },
                { title: 'Archived Subjects', value: this.cardValue('archivedSubjects'), image: archivedImage },
                { title: 'Disabled Lecturers', value: this.cardValue('disabledLecturers'), image: disabledImage },
            ];
        },
    },

    /**
     * Asks PHP for the totals as soon as the page opens
     */
    async mounted() {
        try {
            this.summary = await getDashboardSummary();
        } catch (error) {
            // PHP's own message if it sent one, otherwise one that still says what failed
            this.summaryError = error.response?.data?.error || 'Unable to load the dashboard totals';
        } finally {
            this.isLoading = false;
        }
    },

    methods: {
        /**
         * Picks the number to show on one card
         * @param name which total, i.e. 'totalSubjects'
         * @returns the number, ... while still loading, or a dash if the totals didn't load
         */
        cardValue(name) {
            if (this.isLoading) {
                return '...';
            }

            if (this.summary === null) {
                return '-';
            }

            return this.summary[name];
        },
    },
};
</script>
