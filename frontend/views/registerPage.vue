<script>
import RegistrationForm from '../components/forms/registrationForm.vue';

export default {
    components: {
        RegistrationForm,
    },
};
</script>

<template>
    <div class="container py-5">
        <div class="row align-items-center g-4 g-lg-5">
            <section class="col-12 col-md-6">
                <h1>RevisionHub Account Registration</h1>
                <p>
                    Create your RevisionHub account to access practice tests, revision materials, subject-based test
                    banks, and personalized study plans for exam revision.
                </p>
            </section>

            <section class="col-12 col-md-6">
                <RegistrationForm />
            </section>
        </div>
    </div>
</template>
