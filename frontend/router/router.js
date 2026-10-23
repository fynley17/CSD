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
import LecturerDashboard from '../views/lecturerDashboard.vue';
// student pages are protected by the requiredRoles check in the route guard
import TestSelectionPage from '../views/testSelectionPage.vue';
import Leaderboard from '../views/leaderboard.vue';
import StudentResults from '../views/studentResults.vue';
// lecturer pages are protected by the requiredRoles check in the route guard
import StudentManagement from '../views/studentManagement.vue';
import TestManagement from '../views/testManagement.vue';
import QuestionManagement from '../views/questionManagement.vue';
// administrator pages, only admins get in
import LecturerManagement from '../views/lecturerManagement.vue';
import SubjectManagement from '../views/subjectManagement.vue';
import CohortManagement from '../views/cohortManagement.vue';

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
    {
        path: '/lecturer-dashboard',
        name: 'lecturerDashboard',
        component: LecturerDashboard,
        meta: { title: 'Lecturer dashboard | RevisionHub', requiredRoles: ['lecturer'] },
    },
    {
        path: '/student-dashboard',
        name: 'studentDashboard',
        redirect: { name: 'testSelection' },
    },
    {
        path: '/test-selection',
        alias: '/testing-selection',
        name: 'testSelection',
        component: TestSelectionPage,
        meta: { title: 'Test Selection | RevisionHub', requiredRoles: ['student'], studentOnly: true },
    },
    {
        path: '/leaderboard',
        name: 'leaderboard',
        component: Leaderboard,
        meta: { title: 'Leaderboard | RevisionHub', requiredRoles: ['student'], studentOnly: true },
    },
    {
        path: '/student-results',
        name: 'studentResults',
        component: StudentResults,
        meta: { title: 'Results | RevisionHub', requiredRoles: ['student'], studentOnly: true },
    },
    {
        path: '/student-management',
        name: 'studentManagement',
        component: StudentManagement,
        meta: { title: 'Student Management | RevisionHub', requiredRoles: ['lecturer'] },
    },
    {
        path: '/test-management',
        name: 'testManagement',
        component: TestManagement,
        meta: { title: 'Test Management | RevisionHub', requiredRoles: ['lecturer'] },
    },
    {
        path: '/question-management',
        name: 'questionManagement',
        component: QuestionManagement,
        meta: { title: 'Question Management | RevisionHub', requiredRoles: ['lecturer'] },
    },
    {
        path: '/lecturer-management',
        name: 'lecturerManagement',
        component: LecturerManagement,
        meta: { title: 'Lecturer management | RevisionHub', requiredRoles: ['admin'] },
    },
    {
        path: '/subject-management',
        name: 'subjectManagement',
        component: SubjectManagement,
        meta: { title: 'Subject management | RevisionHub', requiredRoles: ['admin'] },
    },
    {
        path: '/cohort-management',
        name: 'cohortManagement',
        component: CohortManagement,
        meta: { title: 'Cohort management | RevisionHub', requiredRoles: ['admin'] },
    },
    // Send mistyped or retired URLs back to the public landing page.
    {
        path: '/:pathMatch(.*)*',
        redirect: { name: 'landingPage' },
    },
];

// Creates the router using browser history
const router = createRouter({
    history: createWebHistory(),
    routes,
    linkExactActiveClass: 'routerLinkExactActive',
    scrollBehavior() {
        return { top: 0 };
    },
});

// runs before every page change, private pages ask the backend who's logged in first
// this is what stops the back button showing a private page after logging out
router.beforeEach(async (to) => {
    // pages without role restrictions are public
    if (!to.meta.requiredRoles && !to.meta.studentOnly) {
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

    // Student pages require a student session, not simply any signed-in account.
    if (to.meta.studentOnly && user?.accountType !== 'student') {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (user === null) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    // logged in but the wrong account type for a non-student protected page
    if (to.meta.requiredRoles && !to.meta.requiredRoles.includes(user.accountType)) {
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
