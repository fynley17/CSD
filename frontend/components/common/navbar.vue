<template>
    <a class="skipLink" href="#main-content"> Skip to main content </a>

    <header class="siteHeader">
        <nav class="navbar navbar-expand" aria-label="Main navigation">
            <div class="container-fluid">
                <RouterLink
                    class="navbar-brand"
                    :to="logoDestination"
                    :aria-label="currentUser ? 'RevisionHub dashboard' : 'RevisionHub home'"
                >
                    <img src="../../assets/images/Logo.png" alt="RevisionHub home" class="siteLogo" />
                </RouterLink>

                <!-- the page links for whoever is logged in, the list is in NAVIGATION below -->
                <nav v-if="navigation" :class="navigation.desktopClass" :aria-label="navigation.label">
                    <RouterLink v-for="link in navigation.links" :key="link.path" :to="link.path">
                        {{ link.text }}
                    </RouterLink>
                </nav>

                <!-- Desktop navigation -->
                <div class="headerActions desktopActions">
                    <p v-if="authError" class="navbarError" role="alert">{{ authError }}</p>
                    <!-- admins see the lecturer pages too, so this shows which account is logged in -->
                    <p v-if="isAdmin" class="accountBadge">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        Administrator
                    </p>
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
                    <p v-if="isAdmin" class="accountBadge">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        Administrator
                    </p>
                    <nav v-if="navigation" :class="navigation.mobileClass" :aria-label="navigation.label">
                        <RouterLink
                            v-for="link in navigation.links"
                            :key="link.path"
                            :to="link.path"
                            @click="menuOpen = false"
                        >
                            {{ link.text }}
                        </RouterLink>
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
// Prevent a slower session response from overwriting the result for a newer route.
let authRequest = 0;
const isLecturer = computed(() => currentUser.value?.accountType === 'lecturer');
const isAdmin = computed(() => currentUser.value?.accountType === 'admin');
// the page links for each account type, the desktop header and the phone menu both read from here
// so the two menus always match. An Administrator is a Lecturer too, so admins share the lecturer link styles
const NAVIGATION = {
    student: {
        label: 'Student navigation',
        desktopClass: 'studentNavigation desktopStudentNavigation',
        mobileClass: 'studentNavigation mobileStudentNavigation',
        links: [
            { path: '/test-selection', text: 'Tests' },
            { path: '/leaderboard', text: 'Leaderboard' },
            { path: '/student-results', text: 'Results' },
        ],
    },
    lecturer: {
        label: 'Lecturer navigation',
        desktopClass: 'lecturerNavigation desktopLecturerNavigation',
        mobileClass: 'lecturerNavigation mobileLecturerNavigation',
        links: [
            { path: '/lecturer-dashboard', text: 'Dashboard' },
            { path: '/student-management', text: 'Students' },
            { path: '/test-management', text: 'Tests' },
            { path: '/question-management', text: 'Questions' },
        ],
    },
    admin: {
        label: 'Administrator navigation',
        desktopClass: 'lecturerNavigation desktopLecturerNavigation',
        mobileClass: 'lecturerNavigation mobileLecturerNavigation',
        links: [
            { path: '/admin-dashboard', text: 'Dashboard' },
            { path: '/lecturer-management', text: 'Lecturers' },
            { path: '/subject-management', text: 'Subjects' },
            { path: '/cohort-management', text: 'Cohorts' },
            { path: '/student-management', text: 'Students' },
            { path: '/test-management', text: 'Tests' },
            { path: '/question-management', text: 'Questions' },
        ],
    },
};
// the links for whoever is logged in, null for a visitor or an unknown account type so no links show
const navigation = computed(() => NAVIGATION[currentUser.value?.accountType] || null);
const logoDestination = computed(() => {
    if (isLecturer.value) {
        return '/lecturer-dashboard';
    }

    if (isStudent.value) {
        return '/testing-selection';
    }

    // admins go to their own dashboard - same as lecturers and students
    if (isAdmin.value) {
        return '/admin-dashboard';
    }

    return '/';
});

// Refresh the session after client-side navigation so the correct account links stay visible.
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
