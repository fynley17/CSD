<script>
import { changeEmail, getPendingVerification, resendVerification } from '../../services/verificationService';

// Check your email card, sits inside checkEmailPage.vue
// shown straight after registering, or after trying to login before verifying
// lets the student resend the link, or fix a mistyped address and get the link sent there instead
export default {
    data() {
        return {
            email: '',
            isEmailVerified: false,
            isLoading: true,
            // set when PHP doesn't know which account this browser registered, i.e. the session ran out
            sessionError: '',
            isChangingEmail: false,
            newEmail: '',
            // filled in when the new address box is left empty, '' means it's ok
            fieldError: '',
            // PHP's reply when it turns a request down, i.e. asked again too quickly
            serverError: '',
            successMessage: '',
            isSending: false,
        };
    },
    async mounted() {
        try {
            const details = await getPendingVerification();
            this.email = details.email;
            this.isEmailVerified = details.isEmailVerified;

            // registration still works if the first email fails, so say so and point them at the resend button
            if (this.$route.query.notSent === '1' && !this.isEmailVerified) {
                this.serverError = 'We could not send the email. Use Resend email to try again';
            }
        } catch (error) {
            this.sessionError = error.response?.data?.error || 'Something went wrong. Try again later';
        } finally {
            this.isLoading = false;
        }
    },
    methods: {
        // runs when Resend email is pressed
        async sendAgain() {
            await this.send(() => resendVerification());
        },

        // runs when the new address is sent, a quick empty box check first, then PHP does the proper check
        async submitNewEmail() {
            this.fieldError = this.newEmail.trim() === '' ? 'Enter your email address' : '';

            if (this.fieldError !== '') {
                this.serverError = '';
                this.successMessage = '';
                await this.focusMessage();
                return;
            }

            const result = await this.send(() => changeEmail(this.newEmail.trim()));

            // only close the box once PHP has saved it, so a typo can be fixed without typing it all again
            if (result) {
                this.email = result.email;
                this.closeChangeEmail();
            }
        },

        // shared by both buttons, shows PHP's reply in the message box either way
        async send(request) {
            this.serverError = '';
            this.successMessage = '';
            this.isSending = true;

            try {
                const result = await request();
                this.successMessage = result.message;
                return result;
            } catch (error) {
                // PHP's messages already use the house style wording, so they're shown as they come
                this.serverError = error.response?.data?.error || 'Something went wrong. Try again later';
                return null;
            } finally {
                this.isSending = false;
                await this.focusMessage();
            }
        },

        // moving focus to the message means a screen reader reads it out straight away
        async focusMessage() {
            // the message box needs a moment to appear before it can take focus
            await this.$nextTick();
            this.$refs.messageBox?.focus();
        },

        openChangeEmail() {
            this.isChangingEmail = true;
            this.newEmail = this.email;
            this.fieldError = '';
        },

        closeChangeEmail() {
            this.isChangingEmail = false;
            this.newEmail = '';
            this.fieldError = '';
        },
    },
};
</script>

<template>
    <div class="card surfaceCard loginForm verificationCard">
        <div class="card-body p-4 p-md-5">
            <h1 class="mb-3">Check your email</h1>

            <p v-if="isLoading" class="secondaryText mb-0" role="status">Loading…</p>

            <!-- PHP doesn't know which account this is, so the only way back in is to login -->
            <template v-else-if="sessionError">
                <div class="alert alert-danger d-flex gap-2" role="alert">
                    <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <p class="mb-0"><strong>There is a problem:</strong> {{ sessionError }}</p>
                </div>
                <router-link to="/login" class="headerButton loginButton w-100">Go to login</router-link>
            </template>

            <template v-else-if="isEmailVerified">
                <p>Your email address has been verified. Your account is now waiting for lecturer approval.</p>
                <router-link to="/" class="headerButton loginButton w-100">Back to home page</router-link>
            </template>

            <template v-else>
                <p>
                    We have sent a verification link to <strong class="verificationEmail">{{ email }}</strong
                    >. Select the link in the email to verify your address. The link expires in 24 hours.
                </p>

                <!-- one message box for every reply, it stays until the next button press,
                     house style messages never hide themselves -->
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
                                <a href="#newEmail">{{ fieldError }}</a>
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
                    <p class="mb-0"><strong>Success:</strong> {{ successMessage }}</p>
                </div>

                <!-- novalidate turns off the browser's own pop-ups so only the house style errors show -->
                <form v-if="isChangingEmail" class="mb-4" novalidate @submit.prevent="submitNewEmail">
                    <label for="newEmail" class="form-label">New email address</label>
                    <p id="newEmailHint" class="form-text">The link will be sent here instead</p>
                    <p v-if="fieldError" id="newEmailError" class="fieldError">Error: {{ fieldError }}</p>
                    <input
                        id="newEmail"
                        v-model="newEmail"
                        type="email"
                        class="form-control mb-3"
                        :class="{ inputError: fieldError }"
                        autocomplete="email"
                        placeholder="e.g. ws123456@weston.ac.uk"
                        :aria-invalid="fieldError ? 'true' : 'false'"
                        :aria-describedby="fieldError ? 'newEmailHint newEmailError' : 'newEmailHint'"
                    />
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button type="submit" class="headerButton loginButton flex-fill" :disabled="isSending">
                            Send to this address
                        </button>
                        <button
                            type="button"
                            class="headerButton registerButton flex-fill"
                            :disabled="isSending"
                            @click="closeChangeEmail"
                        >
                            Cancel
                        </button>
                    </div>
                </form>

                <div v-else class="d-flex flex-column flex-sm-row gap-2 mb-4">
                    <button
                        type="button"
                        class="headerButton loginButton flex-fill"
                        :disabled="isSending"
                        @click="sendAgain"
                    >
                        Resend email
                    </button>
                    <button
                        type="button"
                        class="headerButton registerButton flex-fill"
                        :disabled="isSending"
                        @click="openChangeEmail"
                    >
                        Change email address
                    </button>
                </div>

                <p class="secondaryText small mb-0">
                    Not received it? Check your spam or junk folder. If the address above is wrong, use Change email
                    address to fix it.
                </p>
            </template>
        </div>
    </div>
</template>
