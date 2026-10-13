// Application entry point that loads global styles, registers Vue Router and mounts the app
// Import Vue to create the application
import { createApp } from 'vue'

// Load Bootstrap styles and the application's custom design styles
import 'bootstrap/dist/css/bootstrap.min.css'
import './assets/styles/main.css'

// Import the root component and router
import App from './app.vue'
import router from './router/router.js'

// Register the router and mount the application
createApp(App).use(router).mount('#app')
