// Defines the application routes and determines which page is displayed for each URL
// Import Vue Router functions for client-side navigation
import { createRouter, createWebHistory } from 'vue-router';
// asks PHP who's logged in, the guard further down uses it
import { getCurrentUser } from '../services/authService';

// Import the landing page and is displayed when users visit the root URL
import LandingPage from '../views/landingPage.vue';
// Import the Privacy Policy page and is displayed when users visit the privacy policy URL
import PrivacyPolicy from '../views/privacyPolicy.vue';
// Import the Register page and is displayed when users visit the register URL
import RegisterPage from '../views/registerPage.vue';
// Import the Terms and conditions page and is displayed when users visit the terms and conditions URL
import TermsAndConditions from '../views/termsAndConditions.vue';
// one login page for every account type
import LoginPage from '../views/loginPage.vue';
// admin home page, only admins can open it
import AdministratorDashboard from '../views/administratorDashboard.vue';

// Define the application routes
const routes = [
    {
        path: '/',
        name: 'landingPage',
        component: LandingPage,
    },
    {
        path: '/privacy-policy',
        name: 'privacyPolicy',
        component: PrivacyPolicy,
    },
    {
        path: '/register',
        name: 'register',
        // Loads the registration page, which contains the registration form.
        component: RegisterPage,
    },
    {
        path: '/terms',
        name: 'termsAndConditions',
        component: TermsAndConditions,
    },
    {
        path: '/login',
        name: 'login',
        component: LoginPage,
        meta: { title: 'Login | RevisionHub' },
    },
    {
        path: '/admin-dashboard',
        name: 'administratorDashboard',
        component: AdministratorDashboard,
        // requiredRoles is read by the guard below, anyone else gets turned away
        meta: { title: 'Administrator dashboard | RevisionHub', requiredRoles: ['admin'] },
    },
];

// Creates the router using browser history
const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

// runs before every page change, private pages ask the backend who's logged in first
// this is what stops the back button showing a private page after logging out
router.beforeEach(async (to) => {
    // pages with no requiredRoles are public
    if (!to.meta.requiredRoles) {
        return true;
    }

    // ask the backend who's logged in
    let user;
    try {
        user = await getCurrentUser();
    } catch {
        // backend couldn't be reached (i.e. PHP isn't running), so treat it as not logged in
        user = null;
    }

    if (user === null) {
        return { name: 'login' };
    }

    // logged in but the wrong account type for this page
    if (!to.meta.requiredRoles.includes(user.accountType)) {
        return { name: 'landingPage' };
    }

    return true;
});

// sets the browser tab title, house style wants "Page name | RevisionHub"
router.afterEach((to) => {
    document.title = to.meta.title || 'RevisionHub';
});

// Exports the router so it can be registered in main.js
export default router;
