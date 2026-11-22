<script setup>
import { useRoute } from 'vue-router';
import Navbar from './components/common/navbar.vue';
import Footer from './components/common/footer.vue';
import IdleWarning from './components/common/idleWarning.vue';

// pages like the testing page hide the shared navbar and footer
const route = useRoute();
</script>

<template>
    <div class="appLayout">
        <Navbar v-if="!route.meta.hideLayout" />
        <!-- Main content area displayed beneath the fixed header -->

        <main id="main-content" tabindex="-1" class="appMain" :class="{ appMainFullScreen: route.meta.hideLayout }">
            <router-view />
        </main>

        <!-- Main content area displayed above the shared footer -->
        <Footer v-if="!route.meta.hideLayout" />

        <!-- the "Are you still there?" pop-up, here so it can open on any page after 28 minutes idle -->
        <IdleWarning />
    </div>
</template>

<style scoped>
/* moved from main.css, only this component uses these styles */
.appLayout {
    display: flex;
    min-height: 100vh;
    flex-direction: column;
}

/* Allow the main content to take up available space */
.appMain {
    flex: 1;
}

/* No navbar on full screen pages, so no space is reserved for it */
main.appMainFullScreen {
    padding-top: 0;
}
</style>
