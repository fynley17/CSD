// Defines the application routes and determines which page is displayed for each URL
// Import Vue Router functions for client-side navigation
import { createRouter, createWebHistory } from "vue-router";

// Import the landing page and is displayed when users visit the root URL
import LandingPage from "../views/landingPage.vue";

// Define the application routes
const routes = [
  {
    path: "/",
    name: "landingPage",
    component: LandingPage,
  },
];

// Creates the router using browser history
const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Exports the router so it can be registered in main.js
export default router;
