<script>
import { resendLoginCode, verifyLoginCode } from '../../services/authService';

// Security code card, the second step of logging in when MFA is switched on
// loginForm.vue swaps it in once the password is right, by then PHP has already emailed the code
// a backup code goes in the same box for when the email can't be reached
export default {
    props: {
        // where the code went with most of the address hidden, i.e. w***@example.com
        maskedEmail: {
            type: String,
            required: true,
        },
    },
    emits: ['loggedIn', 'locked', 'back'],
    data() {
        return {
            code: '',
            // filled in when the box is left empty, '' means it's ok
            fieldError: '',
            // PHP's reply when it turns something down, i.e. a wrong code or asking again too quickly
            serverError: '',
            successMessage: '',
            isSubmitting: false,
            isSending: false,
        };
    },
    mounted() {
        // straight into the box so the code can be pasted without hunting for it
        // the box's description includes where the code went, so a screen reader still hears that
        this.$refs.codeInput.focus();
    },
    methods: {
        // runs when Continue is pressed, a quick empty box check first, then PHP checks the code
        async submitCode() {
            this.serverError = '';
            this.successMessage = '';
            this.fieldError = this.code.trim() === '' ? 'Enter the security code' : '';

            if (this.fieldError !== '') {
                await this.focusMessage();
                return;
            }

            this.isSubmitting = true;

            try {
                const result = await verifyLoginCode(this.code.trim());
                this.$emit('loggedIn', result);
            } catch (error) {
                // 3 wrong tries locks the account, loginForm shows the same locked pop-up as for passwords
                if (error.response?.status === 429) {
                    this.$emit('locked', error.response.data.error);
                    return;
                }
                // PHP's messages already use the house style wording, so they're shown as they come
                this.serverError = error.response?.data?.error || 'Something went wrong. Try again later';
                await this.focusMessage();
            } finally {
                this.isSubmitting = false;
            }
        },

        // runs when Resend code is pressed, PHP makes them wait a minute between emails
        async sendAgain() {
            this.serverError = '';
            this.successMessage = '';
            this.fieldError = '';
            this.isSending = true;

            try {
                const result = await resendLoginCode();
                this.successMessage = result.message;
            } catch (error) {
                this.serverError = error.response?.data?.error || 'Something went wrong. Try again later';
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
    },
};
</script>

<template>
    <div class="card surfaceCard loginForm verificationCard">
        <div class="card-body p-4 p-md-5">
            <h1 class="mb-3">Check your email</h1>

            <p id="codeSentTo">
                We have sent a 6 digit security code to <strong class="verificationEmail">{{ maskedEmail }}</strong
                >. The code expires in 10 minutes.
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
                            <a href="#securityCode">{{ fieldError }}</a>
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
            <form novalidate @submit.prevent="submitCode">
                <div class="mb-4">
                    <label for="securityCode" class="form-label">Security code</label>
                    <p id="securityCodeHint" class="form-text">
                        The 6 digit code from the email, or one of your backup codes
                    </p>
                    <p v-if="fieldError" id="securityCodeError" class="fieldError">Error: {{ fieldError }}</p>
                    <!-- one-time-code lets phones offer the code from the email, and paste works (WCAG 3.3.8) -->
                    <input
                        id="securityCode"
                        ref="codeInput"
                        v-model="code"
                        type="text"
                        class="form-control"
                        :class="{ inputError: fieldError }"
                        autocomplete="one-time-code"
                        spellcheck="false"
                        :aria-invalid="fieldError ? 'true' : 'false'"
                        :aria-describedby="
                            fieldError ? 'codeSentTo securityCodeHint securityCodeError' : 'codeSentTo securityCodeHint'
                        "
                    />
                </div>

                <!-- every button waits while one request is going, so Continue and Resend can't cross over -->
                <button type="submit" class="headerButton loginButton w-100" :disabled="isSubmitting || isSending">
                    Continue
                </button>
            </form>

            <div class="d-flex flex-column flex-sm-row gap-2 mt-3 mb-4">
                <button
                    type="button"
                    class="headerButton registerButton flex-fill"
                    :disabled="isSubmitting || isSending"
                    @click="sendAgain"
                >
                    Resend code
                </button>
                <!-- the password has to be typed again, that sends a new code -->
                <button
                    type="button"
                    class="headerButton registerButton flex-fill"
                    :disabled="isSubmitting || isSending"
                    @click="$emit('back')"
                >
                    Back to login
                </button>
            </div>

            <p class="secondaryText small mb-0">
                Not received it? Check your spam or junk folder. If you can't get into your email, use one of your
                backup codes or contact your lecturer or an administrator.
            </p>
        </div>
    </div>
</template>
