<script>
import api from "../services/api";
export default {
  data() {
    return {
      cohorts: [],
      cohortsLoading: true,
      cohortsError: "",
    };
  },
  async mounted() {
    try {
      const response = await api.get("/cohorts");
      const cohorts = Array.isArray(response.data) ? response.data : [];
      this.cohorts = cohorts;
      if (!Array.isArray(response.data)) {
        throw new Error("Invalid response");
      }
    } catch (error) {
      this.cohortsError = "Unable to load cohorts. Please try again later.";
      console.error("Cohort load failed:", error);
    } finally {
      this.cohortsLoading = false;
    }
  },
};
</script>

<template>
  <section class="container py-5">
    <h1>RevisionHub Account Registration</h1>
    <p>
      Create your RevisionHub account to access practice tests, revision
      materials, subject-based test banks, and personalized study plans for exam
      revision.
    </p>
  </section>
  <section class="container py-5">
    <form>
      <div class="mb-3">
        <label for="firstName" class="form-label">First Name</label>
        <input type="text" class="form-control" id="firstName" required />
      </div>
      <div class="mb-3">
        <label for="lastName" class="form-label">Last Name</label>
        <input type="text" class="form-control" id="lastName" required />
      </div>
      <div class="mb-3">
        <label for="studentNumber" class="form-label">Student Number</label>
        <input type="text" class="form-control" id="studentNumber" required />
      </div>
      <div class="mb-3">
        <label for="cohort" class="form-label">Cohort</label>
        <select
          class="form-control"
          id="cohort"
          required
          :disabled="cohortsLoading || !!cohortsError"
        >
          <option value="">Select a Cohort</option>
          <option
            v-for="cohort in cohorts"
            :key="cohort.cohortID"
            :value="cohort.cohortID"
          >
            {{ cohort.cohortName }}
          </option>
        </select>
        <small v-if="cohortsLoading" class="text-muted">Loading cohorts…</small>
        <small v-else-if="cohortsError" class="text-danger">{{
          cohortsError
        }}</small>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" required />
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input
          type="password"
          class="form-control"
          id="password"
          minlength="8"
          pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
          title="Use at least 8 characters, including an uppercase letter, a lowercase letter, a number, and a symbol."
          required
        />
        <small class="form-text text-muted">
          At least 8 characters, with an uppercase letter, a lowercase letter, a
          number, and a symbol.
        </small>
      </div>
      <div class="mb-3">
        <label for="confirmPassword" class="form-label">Confirm Password</label>
        <input
          type="password"
          class="form-control"
          id="confirmPassword"
          minlength="8"
          pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
          title="Use at least 8 characters, including an uppercase letter, a lowercase letter, a number, and a symbol."
          required
        />
      </div>
      <button type="submit" class="btn btn-primary">Register</button>
    </form>
  </section>
</template>

<style></style>
