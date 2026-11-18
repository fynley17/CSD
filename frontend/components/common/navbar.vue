<template>
    <a class="skipLink" href="#main-content"> Skip to main content </a>

    <header class="siteHeader" :class="{ adminHeader: isAdmin }">
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

<style scoped>
/* moved from main.css, only this component uses these styles */

/* Shared header */
.siteHeader {
    position: fixed;
    z-index: 1030;
    top: 0;
    right: 0;
    left: 0;
    height: var(--navHeight);
    padding: 0 var(--spaceLg);
    border-bottom: 1px solid var(--colorDivider);
    background-color: var(--colorSurface);
}

.siteHeader .navbar {
    top: 0;
    right: 0;
    left: 0;
    z-index: 1030;
    height: var(--navHeight);
    padding: 0;
    background-color: var(--colorSurface);
    border-bottom: 1px solid var(--colorDivider);
}

.siteHeader .navbar,
.siteHeader [class~='container-fluid'] {
    height: 100%;
}

.siteHeader [class~='navbar-brand'] {
    display: flex;
    align-items: center;
    margin: 0;
    padding: var(--spaceSm) var(--spaceMd);
}

.siteLogo {
    display: block;
    width: 140px;
    max-height: 48px;
    object-fit: contain;
}

/* Header navigation actions */
.headerActions {
    display: flex;
    align-items: center;
    gap: var(--spaceSm);
    margin-left: auto;
}

/* Centre student links on desktop and highlight the active page on every screen size. */
.desktopStudentNavigation {
    position: absolute;
    left: 50%;
    display: flex;
    align-items: center;
    gap: var(--spaceXl);
    transform: translateX(-50%);
}

/* Lecturer navigation link. */
.lecturerNavigation a {
    display: inline-flex;
    min-height: 40px;
    align-items: center;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    color: var(--colorBrand);
    font-weight: 600;
    text-decoration: none;
}

.studentNavigation a {
    display: inline-flex;
    min-height: 40px;
    align-items: center;
    padding: var(--spaceXs) var(--spaceSm);
    border-radius: var(--radiusSm);
    color: var(--colorBrand);
    font-weight: 600;
    text-decoration: none;
}

/* Highlight the active lecturer page. */
.lecturerNavigation a.routerLinkExactActive {
    background-color: var(--colorBrand);
    color: var(--colorSurface);
}

.studentNavigation a.routerLinkExactActive {
    background-color: var(--colorBrand);
    color: var(--colorSurface);
}

.studentNavigation a:hover {
    color: var(--colorPrimary);
    text-decoration: underline;
    text-underline-offset: 4px;
}

.studentNavigation a.routerLinkExactActive:hover {
    background-color: var(--colorBrand);
    color: var(--colorSurface);
}

.navbarError {
    margin: 0;
    color: var(--colorError);
    font-size: var(--fontSizeSmall);
}

.mobileMenuToggle,
.mobileNavigation {
    display: none;
}

/* Lecturer Navigation */

/* Centre lecturer links on desktop. */
.desktopLecturerNavigation {
    position: absolute;
    left: 50%;
    display: flex;
    align-items: center;
    gap: var(--spaceXl);
    transform: translateX(-50%);
}

/* Lecturer link hover effect. */
.lecturerNavigation a:hover {
    color: var(--colorPrimary);
    text-decoration: underline;
    text-underline-offset: 4px;
}

/* Keep the active page highlighted on hover. */
.lecturerNavigation a.routerLinkExactActive:hover {
    background-color: var(--colorBrand);
    color: var(--colorSurface);
}

/* Mobile lecturer navigation. */
@media (max-width: 767.98px) {
    .desktopLecturerNavigation {
        display: none;
    }

    .mobileLecturerNavigation {
        display: flex;
        flex-direction: column;
        gap: var(--spaceSm);
        padding: 0 var(--spaceSm) var(--spaceSm);
    }

    .mobileLecturerNavigation a {
        width: 100%;
    }

    .mobileLecturerNavigation a.routerLinkExactActive {
        padding-left: var(--spaceSm);
    }
}

/* Mobile navigation */
@media (max-width: 767px) {
    .siteHeader {
        padding: 0 var(--spaceSm);
    }

    .siteHeader [class~='container-fluid'] {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--spaceSm);
    }

    .siteLogo {
        width: 110px;
        max-width: 100%;
    }

    .desktopActions {
        display: none;
    }

    .desktopStudentNavigation {
        display: none;
    }

    .mobileMenuToggle {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: var(--spaceXs);
        width: 44px;
        height: 44px;
        padding: var(--spaceSm);
        border: 1px solid var(--colorDivider);
        border-radius: var(--radiusSm);
        background-color: var(--colorSurface);
        cursor: pointer;
    }

    .mobileMenuToggle span {
        display: block;
        width: 100%;
        height: 2px;
        background-color: var(--colorPrimary);
    }

    .mobileNavigation.isOpen {
        position: absolute;
        top: 100%;
        right: 0;
        display: flex;
        flex-direction: column;
        gap: var(--spaceSm);
        min-width: 160px;
        padding: var(--spaceMd);
        border: 1px solid var(--colorDivider);
        border-radius: var(--radiusSm);
        background-color: var(--colorSurface);
        box-shadow: var(--shadowCard);
    }

    .mobileNavigation .headerButton {
        display: flex;
        justify-content: center;
        white-space: nowrap;
    }

    .mobileStudentNavigation {
        display: flex;
        flex-direction: column;
        gap: var(--spaceSm);
        padding: 0 var(--spaceSm) var(--spaceSm);
    }

    .mobileStudentNavigation a {
        width: 100%;
    }

    .mobileStudentNavigation a.routerLinkExactActive {
        padding-left: var(--spaceSm);
    }

    .mobileNavigation .navbarError {
        padding: 0 var(--spaceSm);
    }
}

/* admins have seven links, which only fit next to the logo from 1300px wide,
   so below that the admin header uses the same menu button as phones do */
@media (max-width: 1299.98px) {
    .adminHeader [class~='container-fluid'] {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--spaceSm);
    }

    .adminHeader .desktopLecturerNavigation,
    .adminHeader .desktopActions {
        display: none;
    }

    .adminHeader .mobileMenuToggle {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: var(--spaceXs);
        width: 44px;
        height: 44px;
        padding: var(--spaceSm);
        border: 1px solid var(--colorDivider);
        border-radius: var(--radiusSm);
        background-color: var(--colorSurface);
        cursor: pointer;
    }

    .adminHeader .mobileMenuToggle span {
        display: block;
        width: 100%;
        height: 2px;
        background-color: var(--colorPrimary);
    }

    .adminHeader .mobileNavigation.isOpen {
        position: absolute;
        top: 100%;
        right: 0;
        display: flex;
        flex-direction: column;
        gap: var(--spaceSm);
        min-width: 160px;
        padding: var(--spaceMd);
        border: 1px solid var(--colorDivider);
        border-radius: var(--radiusSm);
        background-color: var(--colorSurface);
        box-shadow: var(--shadowCard);
    }

    .adminHeader .mobileNavigation .headerButton {
        display: flex;
        justify-content: center;
        white-space: nowrap;
    }

    .adminHeader .mobileLecturerNavigation {
        display: flex;
        flex-direction: column;
        gap: var(--spaceSm);
        padding: 0 var(--spaceSm) var(--spaceSm);
    }

    .adminHeader .mobileLecturerNavigation a {
        width: 100%;
    }

    .adminHeader .mobileLecturerNavigation a.routerLinkExactActive {
        padding-left: var(--spaceSm);
    }
}

/* account icon and type next to Sign out, Royal Blue which matches nav links */
.accountBadge {
    display: flex;
    align-items: center;
    gap: var(--spaceXs);
    margin: 0;
    color: var(--colorBrand);
    font-size: var(--fontSizeSmall);
    font-weight: 600;
    white-space: nowrap;
}

/* lines the account icon up with the links in the hamburger menu */
.mobileNavigation .accountBadge {
    padding: 0 var(--spaceSm);
}

.siteHeader .headerButton {
    white-space: nowrap;
}

/* Accessible skip link, helping keyboard users for example */
.skipLink {
    position: fixed;
    top: var(--spaceSm);
    left: var(--spaceSm);
    z-index: 2000;
    padding: var(--spaceSm) var(--spaceMd);
    transform: translateY(-200%);
    background-color: var(--colorSurface);
    border: 2px solid var(--colorFocus);
    border-radius: var(--radiusSm);
    color: var(--colorBrand);
    font-weight: 600;
}

.skipLink:focus {
    transform: translateY(0);
}

@media (max-width: 639px) {
    .siteHeader {
        padding: 0 var(--spaceMd);
    }

    .siteLogo {
        width: 140px;
    }
}

@media (min-width: 640px) {
    .siteHeader {
        padding: 0 var(--spaceLg);
    }

    .siteHeader [class~='navbar-brand'] {
        padding: var(--spaceSm) var(--spaceMd);
    }

    .siteLogo {
        width: 180px;
    }
}
</style>
