<script setup>
import Navbar from './components/common/navbar.vue';
import Footer from './components/common/footer.vue';
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
    </div>
</template>
