<script setup>
import { useRoute } from 'vue-router';
import Navbar from './components/common/navbar.vue';
import Footer from './components/common/footer.vue';

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
    </div>
</template>
