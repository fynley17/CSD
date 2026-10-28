<script setup>
// Import Vue functions for reactive data and loading the table on mount.
import { computed, onMounted, ref, watch } from 'vue';

// Import the service that retrieves the leaderboard and the cohort's subjects.
import { getLeaderboard } from '../../services/leaderboardService';

// Store the top students and the subjects returned by the API.
const students = ref([]);
const subjects = ref([]);

// An empty string is the Overall option.
const selectedSubjectID = ref('');

// Track whether the API request is still running.
const loading = ref(true);

// Store an error message if the API request fails.
const errorMessage = ref('');

// Only the latest request updates the table, so a slow response for a subject the student
// has already changed away from can't overwrite the one they picked.
let latestRequest = 0;

// Explain what the points are counting for the selected filter.
const rankingDescription = computed(() => {
    const subject = subjects.value.find((item) => item.subjectID === selectedSubjectID.value);
    return subject
        ? `Ranked by points earned on ${subject.subjectName} tests.`
        : 'Ranked by total points across all your subjects.';
});

// Gold, silver and bronze trophies for the top three; the rank number is always shown too.
const trophyClasses = {
    1: 'leaderboardTrophyGold',
    2: 'leaderboardTrophySilver',
    3: 'leaderboardTrophyBronze',
};

// Show whole percentages without a decimal place, otherwise round to one.
function formatScore(value) {
    return `${Number.isInteger(value) ? value : value.toFixed(1)}%`;
}

// Keep the progress bar inside 0 to 100 percent.
function scoreWidth(value) {
    return `${Math.min(Math.max(value, 0), 100)}%`;
}

async function loadLeaderboard() {
    const request = ++latestRequest;
    loading.value = true;
    errorMessage.value = '';

    try {
        const data = await getLeaderboard(selectedSubjectID.value);
        if (request !== latestRequest) {
            return;
        }
        subjects.value = data.subjects;
        students.value = data.students;
    } catch (error) {
        if (request !== latestRequest) {
            return;
        }
        // Show a message if the request fails.
        errorMessage.value = error.response?.data?.error || 'Unable to load the leaderboard. Please try again later.';
    } finally {
        // Stop displaying the loading message whether the request succeeds or fails.
        if (request === latestRequest) {
            loading.value = false;
        }
    }
}

// Reload the table whenever the student picks a different subject.
watch(selectedSubjectID, loadLeaderboard);

// Retrieve the overall leaderboard when the component loads.
onMounted(loadLeaderboard);
</script>

<template>
    <div class="card leaderboardCard">
        <!-- Table heading and subject filter. -->
        <div class="card-body d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <h2 id="leaderboardHeading" class="h5 fw-bold mb-1">Top 5 students</h2>
                <p class="hintText small mb-0">{{ rankingDescription }}</p>
            </div>

            <div class="leaderboardFilter">
                <label for="leaderboardSubject" class="form-label fw-semibold mb-1">Subject</label>
                <select id="leaderboardSubject" v-model="selectedSubjectID" class="form-select leaderboardSelect">
                    <option value="">Overall</option>
                    <option v-for="subject in subjects" :key="subject.subjectID" :value="subject.subjectID">
                        {{ subject.subjectName }}
                    </option>
                </select>
            </div>
        </div>

        <div class="px-3 px-md-4 pb-4">
            <!-- Show a loading message while retrieving the leaderboard. -->
            <div v-if="loading" class="text-center text-secondary py-4" role="status">Loading leaderboard...</div>

            <!-- Show an error if the API request fails. -->
            <div v-else-if="errorMessage" class="text-center text-danger py-4" role="alert">
                {{ errorMessage }}
            </div>

            <!-- Nobody has earned points for this filter yet. -->
            <p v-else-if="students.length === 0" class="text-center text-secondary py-4 mb-0">
                No one has completed a test here yet. Be the first on the leaderboard!
            </p>

            <!-- Tests and the progress bars are hidden on phones so the table fits without scrolling. -->
            <div v-else class="table-responsive">
                <table class="table align-middle mb-0 leaderboardTable" aria-labelledby="leaderboardHeading">
                    <thead>
                        <tr>
                            <th scope="col">Rank</th>
                            <th scope="col">Student</th>
                            <th scope="col" class="d-none d-sm-table-cell text-end">Tests</th>
                            <th scope="col">Average</th>
                            <th scope="col" class="text-end">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(student, index) in students"
                            :key="index"
                            :class="{ leaderboardCurrentStudent: student.isCurrentStudent }"
                        >
                            <td>
                                <span class="leaderboardRank">
                                    <i
                                        v-if="trophyClasses[student.rank]"
                                        class="bi bi-trophy-fill"
                                        :class="trophyClasses[student.rank]"
                                        aria-hidden="true"
                                    ></i>
                                    {{ student.rank }}
                                </span>
                            </td>
                            <th scope="row" class="leaderboardName">
                                {{ student.name }}
                                <span v-if="student.isCurrentStudent" class="badge leaderboardYouBadge">You</span>
                            </th>
                            <td class="d-none d-sm-table-cell text-end">{{ student.testsTaken }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <!-- The percentage beside it is what screen readers read. -->
                                    <div class="progress leaderboardProgress d-none d-sm-flex" aria-hidden="true">
                                        <div
                                            class="progress-bar"
                                            :style="{ width: scoreWidth(student.averageScore) }"
                                        ></div>
                                    </div>
                                    <span>{{ formatScore(student.averageScore) }}</span>
                                </div>
                            </td>
                            <td class="text-end fw-bold">{{ student.points }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Match the student test cards so the page feels like the rest of the site. */
.leaderboardCard {
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusMd);
    box-shadow: var(--shadowCard);
}

/* The filter takes the full width on phones, then sits beside the heading. */
.leaderboardFilter {
    width: 100%;
}

.leaderboardSelect {
    min-height: var(--minTouchTarget);
    border-color: var(--colorBorderInput);
    color: var(--colorText);
}

.leaderboardSelect:focus {
    border-color: var(--colorBorderInput);
    box-shadow: none;
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

.leaderboardTable th,
.leaderboardTable td {
    padding: var(--spaceSm);
}

.leaderboardTable thead th {
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
    font-weight: 600;
    white-space: nowrap;
}

.leaderboardRank {
    display: inline-flex;
    align-items: center;
    gap: var(--spaceXs);
    font-weight: 700;
    white-space: nowrap;
}

.leaderboardTrophyGold {
    color: var(--colorRankGold);
}

.leaderboardTrophySilver {
    color: var(--colorRankSilver);
}

.leaderboardTrophyBronze {
    color: var(--colorRankBronze);
}

.leaderboardName {
    font-weight: 600;
    overflow-wrap: anywhere;
}

/* Highlight the logged in student's own row. */
.leaderboardCurrentStudent > * {
    --bs-table-bg: var(--colorPrimaryLight);
}

.leaderboardYouBadge {
    margin-left: var(--spaceXs);
    background-color: var(--colorPrimary);
    color: var(--colorSurface);
}

.leaderboardProgress {
    width: 5rem;
    height: 0.5rem;
    flex-shrink: 0;
    background-color: var(--colorDisabled);
}

.leaderboardProgress [class~='progress-bar'] {
    background-color: var(--colorPrimary);
}

@media (min-width: 576px) {
    .leaderboardFilter {
        width: auto;
        min-width: 14rem;
    }

    .leaderboardTable th,
    .leaderboardTable td {
        padding: var(--spaceSm) var(--spaceMd);
    }
}

/* Bootstrap animates the progress bar width, so turn that off for students who prefer less motion. */
@media (prefers-reduced-motion: reduce) {
    .leaderboardProgress [class~='progress-bar'] {
        transition: none;
    }
}
</style>
