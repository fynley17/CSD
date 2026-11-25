<script>
import { resetPassword } from '../services/passwordResetService';
// the same rules PHP checks, so most mistakes are caught before anything is sent
import { PASSWORD_RULES, meetsPasswordRules } from '../utils/passwordRules';

// Reset password page, opened from the link in the forgot password email
// the code in the link is all PHP needs to know whose password to change, nothing else on the page can pick the account
export default {
    data() {
        const token = this.$route.query.token;
        return {
            // read straight away, so the page never flashes the wrong state
            token: typeof token === 'string' ? token : '',
            password: '',
            confirmPassword: '',
            isPasswordShown: false,
            // filled in when a box is wrong, '' means that box is ok
            fieldErrors: { password: '', confirmPassword: '' },
            serverError: '',
            successMessage: '',
            isSending: false,
        };
    },
    computed: {
        passwordRules() {
            return PASSWORD_RULES;
        },
        hasFieldErrors() {
            return this.fieldErrors.password !== '' || this.fieldErrors.confirmPassword !== '';
        },
        // the link is missing its code, i.e. only part of it was copied
        hasNoToken() {
            return this.token === '';
        },
    },
    methods: {
        async submitPassword() {
            this.serverError = '';

            if (this.checkFields()) {
                this.isSending = true;
                try {
                    const result = await resetPassword(this.token, this.password, this.confirmPassword);
                    this.successMessage = result.message;
                    // nothing left on the page that could be used again
                    this.password = '';
                    this.confirmPassword = '';
                    this.token = '';
                } catch (error) {
                    this.serverError = error.response?.data?.error || 'Something went wrong. Try again later';
                } finally {
                    this.isSending = false;
                }
            }

            // moving focus to the message means a screen reader reads it out straight away
            await this.$nextTick();
            this.$refs.messageBox?.focus();
        },

        // catches empty boxes, weak passwords and typos before bothering the server
        checkFields() {
            if (this.password === '') {
                this.fieldErrors.password = 'Enter a new password';
            } else if (!meetsPasswordRules(this.password)) {
                this.fieldErrors.password = PASSWORD_RULES;
            } else {
                this.fieldErrors.password = '';
            }

            if (this.confirmPassword === '') {
                this.fieldErrors.confirmPassword = 'Confirm your new password';
            } else if (this.confirmPassword !== this.password) {
                this.fieldErrors.confirmPassword = 'Your passwords do not match';
            } else {
                this.fieldErrors.confirmPassword = '';
            }

            return !this.hasFieldErrors;
        },

        togglePassword() {
            this.isPasswordShown = !this.isPasswordShown;
        },
    },
};
</script>

<template>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                <div class="card surfaceCard loginForm">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="mb-3">Reset your password</h1>

                        <!-- the password has been changed, so the only thing left to do is login -->
                        <template v-if="successMessage">
                            <div ref="messageBox" class="alert successMessage d-flex gap-2" role="status" tabindex="-1">
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="8 12 11 15 16 9" />
                                </svg>
                                <p class="mb-0"><strong>Success:</strong> {{ successMessage }}</p>
                            </div>
                            <router-link to="/login" class="headerButton loginButton w-100">Go to login</router-link>
                        </template>

                        <!-- without the code nothing can be reset, so point them at a new link -->
                        <template v-else-if="hasNoToken">
                            <div class="alert alert-danger d-flex gap-2" role="alert">
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <p class="mb-0">
                                    <strong>There is a problem:</strong> This reset link is not complete. Open the link
                                    from your email again, or ask for a new one
                                </p>
                            </div>
                            <router-link to="/forgot-password" class="headerButton loginButton w-100">
                                Ask for a new link
                            </router-link>
                        </template>

                        <template v-else>
                            <p class="secondaryText mb-4">Choose a new password for your RevisionHub account.</p>

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
                                    <ul class="messageList mb-0">
                                        <li v-if="fieldErrors.password">
                                            <a href="#newPassword">{{ fieldErrors.password }}</a>
                                        </li>
                                        <li v-if="fieldErrors.confirmPassword">
                                            <a href="#confirmPassword">{{ fieldErrors.confirmPassword }}</a>
                                        </li>
                                    </ul>
                                </div>
                                <div v-else>
                                    <p class="mb-0"><strong>There is a problem:</strong> {{ serverError }}</p>
                                    <router-link to="/forgot-password" class="d-inline-block mt-2">
                                        Ask for a new link
                                    </router-link>
                                </div>
                            </div>

                            <!-- novalidate turns off the browser's own pop-ups so only the house style errors show -->
                            <form novalidate @submit.prevent="submitPassword">
                                <div class="mb-4">
                                    <label for="newPassword" class="form-label">New password</label>
                                    <p id="newPasswordHint" class="form-text">{{ passwordRules }}</p>
                                    <p v-if="fieldErrors.password" id="newPasswordError" class="fieldError">
                                        Error: {{ fieldErrors.password }}
                                    </p>
                                    <div class="d-flex gap-2">
                                        <input
                                            id="newPassword"
                                            v-model="password"
                                            :type="isPasswordShown ? 'text' : 'password'"
                                            class="form-control"
                                            :class="{ inputError: fieldErrors.password }"
                                            autocomplete="new-password"
                                            :aria-invalid="fieldErrors.password ? 'true' : 'false'"
                                            :aria-describedby="
                                                fieldErrors.password
                                                    ? 'newPasswordHint newPasswordError'
                                                    : 'newPasswordHint'
                                            "
                                        />
                                        <button
                                            type="button"
                                            class="headerButton registerButton"
                                            aria-controls="newPassword confirmPassword"
                                            :aria-pressed="isPasswordShown ? 'true' : 'false'"
                                            @click="togglePassword"
                                        >
                                            {{ isPasswordShown ? 'Hide' : 'Show' }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="confirmPassword" class="form-label">Confirm new password</label>
                                    <p v-if="fieldErrors.confirmPassword" id="confirmPasswordError" class="fieldError">
                                        Error: {{ fieldErrors.confirmPassword }}
                                    </p>
                                    <input
                                        id="confirmPassword"
                                        v-model="confirmPassword"
                                        :type="isPasswordShown ? 'text' : 'password'"
                                        class="form-control"
                                        :class="{ inputError: fieldErrors.confirmPassword }"
                                        autocomplete="new-password"
                                        :aria-invalid="fieldErrors.confirmPassword ? 'true' : 'false'"
                                        :aria-describedby="
                                            fieldErrors.confirmPassword ? 'confirmPasswordError' : undefined
                                        "
                                    />
                                </div>

                                <button type="submit" class="headerButton loginButton w-100" :disabled="isSending">
                                    {{ isSending ? 'Resetting...' : 'Reset password' }}
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
