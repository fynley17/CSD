<!-- Lecturer table on the admin dashboard, the 5 lecturers who logged in most recently -->
<template>
    <div class="card border-0 rounded-0 h-100">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 fw-bold mb-0">Recent Lecturer Activity</h2>
            <router-link to="/lecturer-management" class="small text-dark">View all lecturers</router-link>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <div v-if="loadError" class="alert alert-danger mb-0" role="alert">
                <strong>There is a problem:</strong> {{ loadError }}
            </div>

            <div v-else class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle mb-0">
                    <!-- screen readers read the caption out, the heading above already says it on screen -->
                    <caption class="visually-hidden">
                        Lecturers, latest login first
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Staff ID</th>
                            <th scope="col">Email</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="5" class="text-center py-4">Loading lecturers</td>
                        </tr>
                        <tr v-else-if="lecturers.length === 0">
                            <td colspan="5" class="text-center py-4">No lecturers to show yet</td>
                        </tr>
                        <template v-else>
                            <tr v-for="lecturer in lecturers" :key="lecturer.staffID">
                                <td>{{ lecturer.name }}</td>
                                <td>{{ lecturer.staffID }}</td>
                                <td>{{ lecturer.email }}</td>
                                <td>
                                    <!-- house style pill, the word is there so it's not shown by colour alone -->
                                    <span
                                        class="statusPill"
                                        :class="lecturer.isEnabled ? 'statusActive' : 'statusDisabled'"
                                    >
                                        {{ lecturer.isEnabled ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>
                                <td>{{ formatLastLogin(lecturer.lastLogin) }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import { getRecentLecturers } from '../../services/administratorService';

export default {
    data() {
        return {
            lecturers: [],
            isLoading: true,
            loadError: '',
        };
    },

    /**
     * Asks PHP for the lecturers as soon as the dashboard opens
     */
    async mounted() {
        try {
            this.lecturers = await getRecentLecturers();
        } catch (error) {
            // PHP's own message if it sent one, otherwise one that still says what failed
            this.loadError = error.response?.data?.error || 'Unable to load the lecturers';
        } finally {
            this.isLoading = false;
        }
    },

    methods: {
        /**
         * Turns the database time into UK date and time, i.e. 29/09/2026, 16:17
         * @param lastLogin the time from PHP, saved in UTC, or null if they've never logged in
         * @returns the date to show in the table
         */
        formatLastLogin(lastLogin) {
            if (lastLogin === null) {
                return 'Never';
            }

            // the Z tells the browser it's UTC, then it's shown in UK time so summer time is right
            const loginTime = new Date(lastLogin.replace(' ', 'T') + 'Z');

            return loginTime.toLocaleString('en-GB', {
                timeZone: 'Europe/London',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
};
</script>
