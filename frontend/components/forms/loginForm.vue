<script>
import { login } from '../../services/authService';

// each account type has its own home after logging in, students, lecturers and admins never share a dashboard
const DASHBOARD_PATHS = {
    admin: '/admin-dashboard',
    lecturer: '/lecturer-dashboard',
    student: '/student-dashboard',
};

// Login form for every account type, sits inside loginPage.vue
// sends the details off in the background so the page never reloads (AJAX)
// and shows any problems in a house style message box above the boxes
export default {
    data() {
        return {
            username: '',
            password: '',
            isPasswordShown: false,
            isSubmitting: false,
            // filled in when a box is left empty, '' means that box is ok
            fieldErrors: { username: '', password: '' },
            // PHP's reply when it turns the login down, i.e. wrong details or a pending account
            serverError: '',
        };
    },
    computed: {
        hasFieldErrors() {
            return this.fieldErrors.username !== '' || this.fieldErrors.password !== '';
        },
    },
    methods: {
        // runs when Login is pressed, a quick empty box check first, then PHP does the proper check
        async submitLogin() {
            this.serverError = '';

            if (!this.checkFields()) {
                // the message box needs a moment to appear before it can take focus
                // moving focus there means a screen reader reads the problem out straight away
                await this.$nextTick();
                this.$refs.messageBox.focus();
                return;
            }

            this.isSubmitting = true;

            try {
                const user = await login(this.username.trim(), this.password);
                // all good, so off to the dashboard for their account type
                this.$router.push(DASHBOARD_PATHS[user.accountType] || '/');
            } catch (error) {
                // PHP's messages already use the house style wording, so they're shown as they come
                this.serverError = error.response?.data?.error || 'Something went wrong. Try again later';
            } finally {
                this.isSubmitting = false;
            }
        },

        // catches empty boxes before bothering the server, and sets the house style error for each one
        checkFields() {
            this.fieldErrors.username = this.username.trim() === '' ? 'Enter your username' : '';
            this.fieldErrors.password = this.password === '' ? 'Enter your password' : '';
            return !this.hasFieldErrors;
        },

        // the Show/Hide button, lets people check what they've typed before logging in
        togglePassword() {
            this.isPasswordShown = !this.isPasswordShown;
        },
    },
};
</script>

<template>
    <div class="card surfaceCard loginForm">
        <div class="card-body p-4 p-md-5">
            <h1 class="mb-1">Welcome back</h1>
            <p class="secondaryText mb-4">Enter your details to login</p>

            <!-- one message box for both kinds of problem, empty boxes or PHP turning the login down
                 it stays put until Login is pressed again, house style messages never hide themselves -->
            <div
                v-if="hasFieldErrors || serverError"
                ref="messageBox"
                class="alert alert-danger d-flex gap-2"
                role="alert"
                tabindex="-1"
            >
                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <div v-if="hasFieldErrors">
                    <h2 class="messageTitle">There is a problem</h2>
                    <!-- each link jumps straight to the box that needs filling in -->
                    <ul class="messageList mb-0">
                        <li v-if="fieldErrors.username">
                            <a href="#username">{{ fieldErrors.username }}</a>
                        </li>
                        <li v-if="fieldErrors.password">
                            <a href="#password">{{ fieldErrors.password }}</a>
                        </li>
                    </ul>
                </div>
                <p v-else class="mb-0"><strong>There is a problem:</strong> {{ serverError }}</p>
            </div>

            <!-- novalidate turns off the browser's own pop-ups so only the house style errors show -->
            <form novalidate @submit.prevent="submitLogin">
                <div class="mb-4">
                    <label for="username" class="form-label">Username</label>
                    <p id="usernameHint" class="form-text">Your student number or staff ID</p>
                    <p v-if="fieldErrors.username" id="usernameError" class="fieldError">
                        Error: {{ fieldErrors.username }}
                    </p>
                    <input
                        id="username"
                        v-model="username"
                        type="text"
                        class="form-control"
                        :class="{ inputError: fieldErrors.username }"
                        autocomplete="username"
                        placeholder="e.g. ws123456"
                        :aria-invalid="fieldErrors.username ? 'true' : 'false'"
                        :aria-describedby="fieldErrors.username ? 'usernameHint usernameError' : 'usernameHint'"
                    />
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <p v-if="fieldErrors.password" id="passwordError" class="fieldError">
                        Error: {{ fieldErrors.password }}
                    </p>
                    <div class="d-flex gap-2">
                        <!-- paste and password managers allowed, WCAG 3.3.8 says login can't rely on memory -->
                        <input
                            id="password"
                            v-model="password"
                            :type="isPasswordShown ? 'text' : 'password'"
                            class="form-control"
                            :class="{ inputError: fieldErrors.password }"
                            autocomplete="current-password"
                            :aria-invalid="fieldErrors.password ? 'true' : 'false'"
                            :aria-describedby="fieldErrors.password ? 'passwordError' : undefined"
                        />
                        <!-- same white and blue look as the Register button in the header -->
                        <button
                            type="button"
                            class="headerButton registerButton"
                            aria-controls="password"
                            :aria-pressed="isPasswordShown ? 'true' : 'false'"
                            @click="togglePassword"
                        >
                            {{ isPasswordShown ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                </div>

                <button type="submit" class="headerButton loginButton w-100" :disabled="isSubmitting">Login</button>
            </form>

            <p class="secondaryText text-center mt-4 mb-0">
                New here? <router-link to="/register">Register</router-link>
            </p>
        </div>
    </div>
</template>
