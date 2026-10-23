<script setup>
import Navbar from './components/common/navbar.vue';
import Footer from './components/common/footer.vue';
import IdleWarning from './components/common/idleWarning.vue';
</script>

<template>
    <div class="appLayout">
        <Navbar />
        <!-- Main content area displayed beneath the fixed header -->

        <main id="main-content" tabindex="-1" class="appMain">
            <router-view />
        </main>

        <!-- Main content area displayed above the shared footer -->
        <Footer />

        <!-- the "Are you still there?" pop-up, here so it can open on any page after 28 minutes idle -->
        <IdleWarning />
    </div>
</template>
