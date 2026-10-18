<script>
import api from '../services/api';

export default {
    data() {
        return {
            cohorts: [],
            cohortsLoading: true,
            cohortsError: '',
            formError: '',
            successMessage: '',
            isSubmitting: false,
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
                throw new Error('Invalid response');
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
        },
    },
};
</script>

<template>
    <section class="container py-5">
        <h1>RevisionHub Account Registration</h1>
        <p>
            Create your RevisionHub account to access practice tests, revision materials, subject-based test banks, and
            personalized study plans for exam revision.
        </p>
    </section>

    <section class="container py-5">
        <form @submit.prevent="submitRegistration">
            <div v-if="formError" class="alert alert-danger" role="alert">
                {{ formError }}
            </div>
            <div v-if="successMessage" class="alert alert-success" role="alert">
                {{ successMessage }}
            </div>

            <div class="mb-3">
                <label for="firstName" class="form-label">First Name</label>
                <input v-model="form.firstName" type="text" class="form-control" id="firstName" required />
            </div>

            <div class="mb-3">
                <label for="lastName" class="form-label">Last Name</label>
                <input v-model="form.lastName" type="text" class="form-control" id="lastName" required />
            </div>

            <div class="mb-3">
                <label for="studentNumber" class="form-label">Student Number</label>
                <input v-model="form.studentNumber" type="text" class="form-control" id="studentNumber" required />
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
                    <option value="">Select a Cohort</option>
                    <option v-for="cohort in cohorts" :key="cohort.cohortID" :value="cohort.cohortID">
                        {{ cohort.cohortName }}
                    </option>
                </select>
                <small v-if="cohortsLoading" class="text-muted">Loading cohorts…</small>
                <small v-else-if="cohortsError" class="text-danger">{{ cohortsError }}</small>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input v-model="form.email" type="email" class="form-control" id="email" required />
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input
                    v-model="form.password"
                    type="password"
                    class="form-control"
                    id="password"
                    minlength="8"
                    pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                    title="Use 8+ characters with uppercase, lowercase, number, and symbol."
                    required
                />
                <small class="form-text text-muted">
                    At least 8 characters, with an uppercase letter, a lowercase letter, a number, and a symbol.
                </small>
            </div>

            <div class="mb-3">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <input
                    v-model="form.confirmPassword"
                    type="password"
                    class="form-control"
                    id="confirmPassword"
                    minlength="8"
                    pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                    title="Use 8+ characters with uppercase, lowercase, number, and symbol."
                    required
                />
            </div>

            <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Registering...' : 'Register' }}
            </button>
        </form>
    </section>
</template>
