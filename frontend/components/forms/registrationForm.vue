<script>
import api from '../../services/api';

export default {
    data() {
        return {
            cohorts: [],
            cohortsLoading: true,
            cohortsError: '',
            formError: '',
            successMessage: '',
            isSubmitting: false,
            termsAccepted: false,
            form: {
                firstName: '',
                lastName: '',
                studentNumber: '',
                cohortID: '',
                email: '',
                password: '',
                confirmPassword: '',
            },
        };
    },
    async mounted() {
        try {
            const response = await api.get('/cohorts');
            if (!Array.isArray(response.data)) {
                throw new Error('Invalid response.');
            }
            this.cohorts = response.data;
        } catch {
            this.cohortsError = 'Unable to load cohorts. Please try again later.';
        } finally {
            this.cohortsLoading = false;
        }
    },
    methods: {
        async submitRegistration() {
            this.formError = '';
            this.successMessage = '';

            if (!this.termsAccepted) {
                this.formError = 'Please agree to the terms and conditions before registering.';
                return;
            }

            if (
                !this.form.firstName ||
                !this.form.lastName ||
                !this.form.studentNumber ||
                !this.form.email ||
                !this.form.password ||
                !this.form.confirmPassword ||
                !this.form.cohortID
            ) {
                this.formError = 'Please complete all required fields.';
                return;
            }

            if (this.form.password !== this.form.confirmPassword) {
                this.formError = 'Passwords do not match.';
                return;
            }

            const selectedCohort = this.cohorts.find(
                (cohort) => String(cohort.cohortID) === String(this.form.cohortID),
            );
            if (!selectedCohort) {
                this.formError =
                    'The selected cohort is no longer available. Please reload the page and select it again.';
                return;
            }

            this.isSubmitting = true;

            try {
                const payload = {
                    firstName: this.form.firstName.trim(),
                    lastName: this.form.lastName.trim(),
                    studentNumber: this.form.studentNumber.trim(),
                    email: this.form.email.trim(),
                    password: this.form.password,
                    confirmPassword: this.form.confirmPassword,
                    cohortID: selectedCohort.cohortID,
                };

                const response = await api.post('/register', payload);
                this.successMessage = response.data?.message || 'Registration successful.';
                this.resetForm();
            } catch (error) {
                this.formError =
                    error.response?.data?.error ||
                    error.response?.data?.message ||
                    'Unable to register. Please try again.';
            } finally {
                this.isSubmitting = false;
            }
        },
        resetForm() {
            this.form = {
                firstName: '',
                lastName: '',
                studentNumber: '',
                cohortID: '',
                email: '',
                password: '',
                confirmPassword: '',
            };
            this.termsAccepted = false;
        },
    },
};
</script>

<template>
    <div class="card surfaceCard">
        <div class="card-body p-4">
            <form @submit.prevent="submitRegistration">
                <div v-if="formError" class="alert alert-danger" role="alert">
                    {{ formError }}
                </div>
                <div v-if="successMessage" class="alert alert-success" role="alert">
                    {{ successMessage }}
                </div>

                <div class="mb-3">
                    <label for="firstName" class="form-label">First name</label>
                    <input
                        v-model="form.firstName"
                        type="text"
                        class="form-control"
                        id="firstName"
                        placeholder="For example: Fyn"
                        required
                    />
                </div>

                <div class="mb-3">
                    <label for="lastName" class="form-label">Last name</label>
                    <input
                        v-model="form.lastName"
                        type="text"
                        class="form-control"
                        id="lastName"
                        placeholder="For example: Geary"
                        required
                    />
                </div>

                <div class="mb-3">
                    <label for="studentNumber" class="form-label">Student number</label>
                    <input
                        v-model="form.studentNumber"
                        type="text"
                        class="form-control"
                        id="studentNumber"
                        placeholder="For example: ws381211"
                        required
                    />
                </div>

                <div class="mb-3">
                    <label for="cohort" class="form-label">Cohort</label>
                    <select
                        v-model="form.cohortID"
                        class="form-control"
                        id="cohort"
                        required
                        :disabled="cohortsLoading || !!cohortsError"
                    >
                        <option value="">Select a cohort</option>
                        <option v-for="cohort in cohorts" :key="cohort.cohortID" :value="cohort.cohortID">
                            {{ cohort.cohortName }}
                        </option>
                    </select>
                    <small v-if="cohortsLoading" class="text-muted">Loading cohorts…</small>
                    <small v-else-if="cohortsError" class="text-danger">{{ cohortsError }}</small>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="form-control"
                        id="email"
                        placeholder="For example: fyn@example.com"
                        required
                    />
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="form-control"
                        id="password"
                        placeholder="For example: River!28Lamp"
                        minlength="8"
                        pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                        title="Use 8+ characters with uppercase, lowercase, a number, and a symbol."
                        required
                    />
                    <small class="form-text text-muted">
                        Use your own password, not the example. It must have at least 8 characters, including an
                        uppercase letter, a lowercase letter, a number, and a symbol.
                    </small>
                </div>

                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm password</label>
                    <input
                        v-model="form.confirmPassword"
                        type="password"
                        class="form-control"
                        id="confirmPassword"
                        placeholder="For example: River!28Lamp"
                        minlength="8"
                        pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                        title="Re-enter password."
                        required
                    />
                </div>

                <div class="form-check mb-3">
                    <input
                        v-model="termsAccepted"
                        type="checkbox"
                        class="form-check-input"
                        id="termsAccepted"
                        required
                    />
                    <label class="form-check-label" for="termsAccepted">
                        I agree to the <a href="/terms" target="_blank">terms and conditions</a>.
                    </label>
                </div>

                <button type="submit" class="headerButton registerButton" :disabled="isSubmitting || !termsAccepted">
                    {{ isSubmitting ? 'Registering...' : 'Create Revision Account' }}
                </button>
            </form>
        </div>
    </div>
</template>
