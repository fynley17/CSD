<script>
import { requestPasswordReset } from '../services/passwordResetService';

// Forgot password page, opened from the link under the login form
// the student or staff member types their email and PHP sends them a link to reset their password
// PHP's reply is the same whether or not the email has an account, so nobody can use it to find out who's registered
export default {
    data() {
        return {
            email: '',
            // filled in when the box is left empty, '' means it's ok
            fieldError: '',
            // PHP turning the request down, i.e. the wrong format
            serverError: '',
            successMessage: '',
            isSending: false,
        };
    },
    methods: {
        async submitEmail() {
            this.fieldError = this.email.trim() === '' ? 'Enter your email address' : '';
            this.serverError = '';
            this.successMessage = '';

            if (this.fieldError === '') {
                this.isSending = true;
                try {
                    const result = await requestPasswordReset(this.email.trim());
                    this.successMessage = result.message;
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
    },
};
</script>

<template>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                <div class="card surfaceCard loginForm">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="mb-1">Forgot your password?</h1>
                        <p class="secondaryText mb-4">
                            Enter the email address on your account and we will send you a link to reset your password.
                        </p>

                        <div
                            v-if="fieldError || serverError"
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
                            <div v-if="fieldError">
                                <h2 class="messageTitle">There is a problem</h2>
                                <ul class="messageList mb-0">
                                    <li>
                                        <a href="#email">{{ fieldError }}</a>
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="mb-0"><strong>There is a problem:</strong> {{ serverError }}</p>
                        </div>
                        <div
                            v-else-if="successMessage"
                            ref="messageBox"
                            class="alert successMessage d-flex gap-2"
                            role="status"
                            tabindex="-1"
                        >
                            <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="8 12 11 15 16 9" />
                            </svg>
                            <p class="mb-0">
                                <strong>Success:</strong> {{ successMessage }}. Check your spam or junk folder if it
                                does not arrive.
                            </p>
                        </div>

                        <!-- novalidate turns off the browser's own pop-ups so only the house style errors show -->
                        <form novalidate @submit.prevent="submitEmail">
                            <div class="mb-4">
                                <label for="email" class="form-label">Email address</label>
                                <p v-if="fieldError" id="emailError" class="fieldError">Error: {{ fieldError }}</p>
                                <input
                                    id="email"
                                    v-model="email"
                                    type="email"
                                    class="form-control"
                                    :class="{ inputError: fieldError }"
                                    autocomplete="email"
                                    placeholder="e.g. ws123456@weston.ac.uk"
                                    :aria-invalid="fieldError ? 'true' : 'false'"
                                    :aria-describedby="fieldError ? 'emailError' : undefined"
                                />
                            </div>

                            <button type="submit" class="headerButton loginButton w-100" :disabled="isSending">
                                {{ isSending ? 'Sending...' : 'Send reset link' }}
                            </button>
                        </form>

                        <p class="secondaryText text-center mt-4 mb-0">
                            Remembered it? <router-link to="/login">Back to login</router-link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
