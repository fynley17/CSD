<template>
    <a class="skipLink" href="#main-content"> Skip to main content </a>

    <header class="siteHeader">
        <nav class="navbar navbar-expand" aria-label="Main navigation">
            <div class="container-fluid">
                <a class="navbar-brand" href="/" aria-label="RevisionHub home">
                    <img src="../../assets/images/Logo.png" alt="RevisionHub home" class="siteLogo" />
                </a>

                <!-- Desktop navigation -->
                <div class="headerActions desktopActions">
                    <a href="/login" class="headerButton loginButton">Sign in</a>
                    <a href="/register" class="headerButton registerButton"> Register </a>
                </div>

                <!-- Mobile hamburger button -->
                <button
                    class="mobileMenuToggle"
                    type="button"
                    :aria-expanded="menuOpen"
                    aria-controls="mobileNavigation"
                    :aria-label="menuOpen ? 'Close navigation menu' : 'Open navigation menu'"
                    @click="menuOpen = !menuOpen"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <!-- Mobile dropdown -->
                <div id="mobileNavigation" class="mobileNavigation" :class="{ isOpen: menuOpen }" :hidden="!menuOpen">
                    <a href="/login" class="headerButton loginButton" @click="menuOpen = false"> Sign in </a>
                    <a href="/register" class="headerButton registerButton" @click="menuOpen = false"> Register </a>
                </div>
            </div>
        </nav>
    </header>
</template>

<script setup>
import { ref } from 'vue';

const menuOpen = ref(false);
</script>
