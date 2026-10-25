<script>
import { verifyEmail } from '../services/verificationService';

// Verify email page, the link in the verification email opens this
// it sends the code from the link to PHP straight away and shows whether it worked
export default {
    data() {
        return {
            isVerifying: true,
            isVerified: false,
            serverError: '',
        };
    },
    async mounted() {
        const token = this.$route.query.token;

        // no code in the link, i.e. it was only partly copied, so there's nothing to send
        if (typeof token !== 'string' || token === '') {
            this.serverError = 'This link is not valid. Check you copied the whole link from the email';
            this.isVerifying = false;
            return;
        }

        try {
            await verifyEmail(token);
            this.isVerified = true;
        } catch (error) {
            // PHP's messages already use the house style wording, so they're shown as they come
            this.serverError = error.response?.data?.error || 'Something went wrong. Try again later';
        } finally {
            this.isVerifying = false;
        }
    },
};
</script>

<template>
    <div class="container py-5">
        <!-- keeps the card narrow and in the middle, full width on phones -->
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card surfaceCard loginForm verificationCard">
                    <div class="card-body p-4 p-md-5">
                        <template v-if="isVerifying">
                            <h1 class="mb-3">Verifying your email</h1>
                            <p class="secondaryText mb-0" role="status">Please wait…</p>
                        </template>

                        <template v-else-if="isVerified">
                            <h1 class="mb-3">Email verified</h1>
                            <div class="alert successMessage d-flex gap-2" role="status">
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="8 12 11 15 16 9" />
                                </svg>
                                <p class="mb-0"><strong>Success:</strong> Your email address has been verified</p>
                            </div>
                            <p>
                                Your account is now waiting for lecturer approval. We have sent you a welcome email, and
                                we will email you again as soon as your account is ready.
                            </p>
                            <router-link to="/" class="headerButton loginButton w-100">Back to home page</router-link>
                        </template>

                        <template v-else>
                            <h1 class="mb-3">We could not verify your email</h1>
                            <div class="alert alert-danger d-flex gap-2" role="alert">
                                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <p class="mb-0"><strong>There is a problem:</strong> {{ serverError }}</p>
                            </div>
                            <!-- logging in with the right password takes them to check your email to get a new link -->
                            <router-link to="/login" class="headerButton loginButton w-100">Go to login</router-link>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
