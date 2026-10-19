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
    <div class="loginCard">
        <h1 class="loginTitle">Welcome back</h1>
        <p class="loginIntro">Enter your details to login</p>

        <!-- one message box for both kinds of problem, empty boxes or PHP turning the login down
             it stays put until Login is pressed again, house style messages never hide themselves -->
        <div v-if="hasFieldErrors || serverError" ref="messageBox" class="messageBox" role="alert" tabindex="-1">
            <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <div v-if="hasFieldErrors">
                <h2 class="messageTitle">There is a problem</h2>
                <!-- each link jumps straight to the box that needs filling in -->
                <ul class="messageList">
                    <li v-if="fieldErrors.username">
                        <a href="#username">{{ fieldErrors.username }}</a>
                    </li>
                    <li v-if="fieldErrors.password">
                        <a href="#password">{{ fieldErrors.password }}</a>
                    </li>
                </ul>
            </div>
            <p v-else class="messageText"><strong>There is a problem:</strong> {{ serverError }}</p>
        </div>

        <!-- novalidate turns off the browser's own pop-ups so only the house style errors show -->
        <form novalidate @submit.prevent="submitLogin">
            <div class="loginField">
                <label for="username" class="loginLabel">Username</label>
                <p id="usernameHint" class="loginHint">Your student number or staff ID</p>
                <p v-if="fieldErrors.username" id="usernameError" class="fieldError">
                    Error: {{ fieldErrors.username }}
                </p>
                <input
                    id="username"
                    v-model="username"
                    type="text"
                    class="loginInput"
                    :class="{ inputError: fieldErrors.username }"
                    autocomplete="username"
                    placeholder="e.g. ws123456"
                    :aria-invalid="fieldErrors.username ? 'true' : 'false'"
                    :aria-describedby="fieldErrors.username ? 'usernameHint usernameError' : 'usernameHint'"
                />
            </div>

            <div class="loginField">
                <label for="password" class="loginLabel">Password</label>
                <p v-if="fieldErrors.password" id="passwordError" class="fieldError">
                    Error: {{ fieldErrors.password }}
                </p>
                <div class="passwordRow">
                    <!-- paste and password managers allowed on purpose, WCAG 3.3.8 says login can't rely on memory -->
                    <input
                        id="password"
                        v-model="password"
                        :type="isPasswordShown ? 'text' : 'password'"
                        class="loginInput"
                        :class="{ inputError: fieldErrors.password }"
                        autocomplete="current-password"
                        :aria-invalid="fieldErrors.password ? 'true' : 'false'"
                        :aria-describedby="fieldErrors.password ? 'passwordError' : undefined"
                    />
                    <button
                        type="button"
                        class="showButton"
                        aria-controls="password"
                        :aria-pressed="isPasswordShown ? 'true' : 'false'"
                        @click="togglePassword"
                    >
                        {{ isPasswordShown ? 'Hide' : 'Show' }}
                    </button>
                </div>
            </div>

            <button type="submit" class="headerButton loginButton loginSubmit" :disabled="isSubmitting">Login</button>
        </form>

        <p class="loginRegister">New here? <router-link to="/register">Register</router-link></p>
    </div>
</template>

<style scoped>
/* the white card, same border, corners and shadow as the other cards on the site
   max width keeps the form narrow so it's easy to read */
.loginCard {
    max-width: 30rem;
    margin: 0 auto;
    padding: var(--spaceXl) var(--spaceLg);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusMd);
    box-shadow: var(--shadowCard);
}

.loginTitle {
    margin: 0 0 var(--spaceXs);
}

.loginIntro,
.loginHint,
.loginRegister {
    color: var(--colorTextSecondary);
}

.loginIntro {
    margin: 0 0 var(--spaceLg);
}

.loginField {
    margin: 0 0 var(--spaceLg);
}

.loginLabel {
    display: block;
    margin: 0 0 var(--spaceXs);
    font-weight: 600;
}

.loginHint {
    margin: 0 0 var(--spaceXs);
    font-size: var(--fontSizeSmall);
}

/* 44px tall boxes with a mid grey border (house style 5)
   mid grey because the lighter divider grey fails contrast on white */
.loginInput {
    width: 100%;
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    color: var(--colorText);
    font-family: var(--fontBody);
    font-size: var(--fontSizeBody);
}

.loginInput::placeholder {
    color: var(--colorBorderInput);
}

/* keyboard users need to see which box they're in, 3px blue outline, never removed */
.loginInput:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

/* red 2px border on a box that was left empty, the Error: text above it means it's not colour alone */
.inputError {
    border: 2px solid var(--colorError);
}

.fieldError {
    margin: 0 0 var(--spaceXs);
    color: var(--colorError);
    font-weight: 600;
}

.passwordRow {
    display: flex;
    gap: var(--spaceSm);
}

/* Show button, styled as a secondary button i.e. blue text and border on white */
.showButton {
    min-height: 44px;
    padding: 0 var(--spaceMd);
    background-color: var(--colorSurface);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    color: var(--colorPrimary);
    font-weight: 600;
}

.showButton:hover {
    background-color: var(--colorPrimaryLight);
}

.loginSubmit {
    width: 100%;
}

.loginRegister {
    margin: var(--spaceLg) 0 0;
    text-align: center;
}

/* house style message box, light red background, thick red left edge, icon, dark text */
.messageBox {
    display: flex;
    gap: var(--spaceSm);
    margin: 0 0 var(--spaceLg);
    padding: var(--spaceMd);
    background-color: var(--colorErrorLight);
    border-left: 6px solid var(--colorError);
    color: var(--colorText);
}

/* the summary gets focus when it pops up, so it needs the outline as well */
.messageBox:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

/* stops the icon getting squashed when the message is long */
.messageIcon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    fill: none;
    stroke: var(--colorError);
    stroke-width: 2;
    stroke-linecap: round;
}

.messageTitle {
    margin: 0 0 var(--spaceXs);
    color: var(--colorText);
    font-family: var(--fontBody);
    font-size: var(--fontSizeBody);
}

.messageText {
    margin: 0;
}

.messageList {
    margin: 0;
    padding: 0 0 0 var(--spaceLg);
}

.messageList a {
    color: var(--colorError);
}
</style>
