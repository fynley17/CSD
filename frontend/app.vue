<!-- Root component that displays the shared components and the current page -->
<script setup>
// Import the shared navbar so it appears across application pages
import Navbar from './components/common/navbar.vue'
</script>

<template>
  <!-- Keep the navbar outside the router view so it remains visible on every page -->
  <Navbar/>
  <!-- Main content area displayed beneath the fixed header -->
  <main id="main-content" tabindex="-1">
    <router-view/>
  </main>
</template>
