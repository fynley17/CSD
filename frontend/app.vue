<script setup>
import Navbar from "./components/common/navbar.vue";
import Footer from "./components/common/footer.vue";
</script>

<template>
  <div class="app-layout">
    <Navbar />
     <!-- Main content area displayed beneath the fixed header -->

    <main id="main-content" tabindex="-1" class="app-main">
      <router-view />
    </main>

    <!-- Main content area displayed above the shared footer -->
    <Footer />
  </div>
</template>