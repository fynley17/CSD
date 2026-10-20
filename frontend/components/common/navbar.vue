<template>
    <a class="skipLink" href="#main-content"> Skip to main content </a>

    <header class="siteHeader">
        <nav class="navbar navbar-expand" aria-label="Main navigation">
            <div class="container-fluid">
                <RouterLink class="navbar-brand" to="/" aria-label="RevisionHub home">
                    <img src="../../assets/images/Logo.png" alt="RevisionHub home" class="siteLogo" />
                </RouterLink>

                <nav
                    v-if="isStudent"
                    class="studentNavigation desktopStudentNavigation"
                    aria-label="Student navigation"
                >
                    <RouterLink to="/testing-selection">Tests</RouterLink>
                    <RouterLink to="/leaderboard">Leaderboard</RouterLink>
                    <RouterLink to="/student-results">Results</RouterLink>
                </nav>

                <!-- Desktop navigation -->
                <div class="headerActions desktopActions">
                    <p v-if="authError" class="navbarError" role="alert">{{ authError }}</p>
                    <button
                        v-if="currentUser"
                        type="button"
                        class="headerButton loginButton"
                        :disabled="isSigningOut"
                        @click="signOut"
                    >
                        {{ isSigningOut ? 'Signing out...' : 'Sign out' }}
                    </button>
                    <template v-else-if="authChecked">
                        <RouterLink to="/login" class="headerButton loginButton">Sign in</RouterLink>
                        <RouterLink to="/register" class="headerButton registerButton">Register</RouterLink>
                    </template>
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
                    <nav
                        v-if="isStudent"
                        class="studentNavigation mobileStudentNavigation"
                        aria-label="Student navigation"
                    >
                        <RouterLink to="/testing-selection" @click="menuOpen = false">Tests</RouterLink>
                        <RouterLink to="/leaderboard" @click="menuOpen = false">Leaderboard</RouterLink>
                        <RouterLink to="/student-results" @click="menuOpen = false">Results</RouterLink>
                    </nav>
                    <p v-if="authError" class="navbarError" role="alert">{{ authError }}</p>
                    <button
                        v-if="currentUser"
                        type="button"
                        class="headerButton loginButton"
                        :disabled="isSigningOut"
                        @click="signOut"
                    >
                        {{ isSigningOut ? 'Signing out...' : 'Sign out' }}
                    </button>
                    <template v-else-if="authChecked">
                        <RouterLink to="/login" class="headerButton loginButton" @click="menuOpen = false">
                            Sign in
                        </RouterLink>
                        <RouterLink to="/register" class="headerButton registerButton" @click="menuOpen = false">
                            Register
                        </RouterLink>
                    </template>
                </div>
            </div>
        </nav>
    </header>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { getCurrentUser, logout } from '../../services/authService';

const menuOpen = ref(false);
const currentUser = ref(null);
const authChecked = ref(false);
const authError = ref('');
const isSigningOut = ref(false);
const route = useRoute();
const router = useRouter();
const isStudent = computed(() => currentUser.value?.accountType === 'student');
let authRequest = 0;

watch(
    () => route.fullPath,
    async () => {
        const request = ++authRequest;
        menuOpen.value = false;
        authError.value = '';

        try {
            const user = await getCurrentUser();
            if (request === authRequest) {
                currentUser.value = user;
                authChecked.value = true;
            }
        } catch {
            if (request === authRequest) {
                currentUser.value = null;
                authChecked.value = true;
                authError.value = 'Unable to check your sign-in status.';
            }
        }
    },
    { immediate: true },
);

async function signOut() {
    if (isSigningOut.value) {
        return;
    }

    isSigningOut.value = true;
    authError.value = '';

    try {
        await logout();
        currentUser.value = null;
        menuOpen.value = false;
        await router.push('/');
    } catch {
        authError.value = 'Unable to sign out. Please try again.';
    } finally {
        isSigningOut.value = false;
    }
}
</script>
