<script>
import { setPassword } from '../services/passwordSetupService';

export default {
    name: 'SetupPasswordPage',

    data() {
        return {
            password: '',
            confirmPassword: '',
            loading: false,
            completed: false,
            errorMessage: '',
            successMessage: '',
            token: '',
        };
    },

    computed: {
        passwordsMatch() {
            return this.password === this.confirmPassword;
        },

        passwordIsValid() {
            return (
                this.password.length >= 8 &&
                /[A-Z]/.test(this.password) &&
                /[a-z]/.test(this.password) &&
                /\d/.test(this.password) &&
                /[^A-Za-z0-9]/.test(this.password)
            );
        },
    },

    mounted() {
        const token = this.$route.query.token;

        if (typeof token !== 'string' || !token) {
            this.errorMessage = 'This password setup link is invalid or missing.';
            return;
        }

        this.token = token;
    },

    methods: {
        async submitPassword() {
            this.errorMessage = '';
            this.successMessage = '';

            if (!this.passwordIsValid) {
                this.errorMessage = [
                    'Please use at least 8 characters, including an uppercase letter,',
                    'a lowercase letter, a number and a symbol.',
                ].join(' ');
                return;
            }

            if (!this.passwordsMatch) {
                this.errorMessage = 'Your passwords do not match.';
                return;
            }

            this.loading = true;

            try {
                const response = await setPassword(this.token, this.password, this.confirmPassword);

                this.completed = true;
                this.successMessage = response.message || 'Your password has been set. You can now log in.';
                this.password = '';
                this.confirmPassword = '';
            } catch (error) {
                this.errorMessage = error.response?.data?.error || 'Something went wrong. Please try again.';
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<template>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card surfaceCard loginForm verificationCard">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h1 class="h3">Set your password</h1>
                            <p class="text-muted">Create a password for your RevisionHub account.</p>
                        </div>

                        <div v-if="errorMessage" class="alert alert-danger" role="alert">
                            {{ errorMessage }}
                        </div>

                        <div v-if="completed" class="text-center">
                            <div class="alert alert-success" role="status">
                                {{ successMessage }}
                            </div>

                            <router-link to="/login" class="btn btn-primary"> Go to login </router-link>
                        </div>

                        <form v-else @submit.prevent="submitPassword">
                            <div class="mb-3">
                                <label for="password" class="form-label"> New password </label>

                                <input
                                    id="password"
                                    v-model="password"
                                    type="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                    :disabled="loading || !token"
                                />

                                <small class="form-text text-muted">
                                    At least 8 characters, with uppercase, lowercase, number and symbol.
                                </small>
                            </div>

                            <div class="mb-4">
                                <label for="confirmPassword" class="form-label"> Confirm password </label>

                                <input
                                    id="confirmPassword"
                                    v-model="confirmPassword"
                                    type="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                    :disabled="loading || !token"
                                />
                            </div>

                            <button type="submit" class="btn btn-primary w-100" :disabled="loading || !token">
                                {{ loading ? 'Setting password...' : 'Set password' }}
                            </button>
                        </form>

                        <div v-if="!completed" class="text-center mt-4">
                            <router-link to="/login"> Back to login </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
