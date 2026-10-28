<template>
    <section class="container py-4">
        <h1>Leaderboard</h1>
        <p class="hintText">See who is top of your cohort, overall or for each of your subjects.</p>

        <LeaderboardTable class="mt-4" />
    </section>
</template>

<script setup>
import LeaderboardTable from '../components/tables/leaderboardTable.vue';
</script>
