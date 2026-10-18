<script>
import RegistrationForm from '../components/forms/registrationForm.vue';
import registrationHero from '../assets/images/registration-hero.svg';

export default {
    components: {
        RegistrationForm,
    },
    data() {
        return {
            registrationHero,
        };
    },
};
</script>

<template>
    <div class="container py-5">
        <div class="row justify-content-center align-items-stretch g-4 g-lg-5">
            <!-- Keep the introduction and study benefits beside the form on wider screens. -->
            <section class="registrationIntro col-12 col-md-6 col-xl-5 d-flex flex-column">
                <header>
                    <h1 class="registrationIntroTitle">RevisionHub Account Registration</h1>
                    <p class="registrationIntroText">
                        Create your RevisionHub account to access practice tests, revision materials, subject-based test
                        banks, and personalised study plans for exam revision.
                    </p>
                </header>

                <!-- This illustration is hidden on small screens to keep the form in focus. -->
                <div class="registrationHeroFrame ratio ratio-4x3 d-none d-md-block overflow-hidden">
                    <img
                        :src="registrationHero"
                        class="registrationHeroImage w-100 h-100 object-fit-cover"
                        alt="Illustration of a student studying at a laptop"
                    />
                </div>

                <ul class="registrationFeatures list-unstyled mb-3 mb-md-0 mt-4">
                    <li class="d-flex gap-3 mb-4">
                        <span class="registrationFeatureNumber align-self-start">1</span>
                        <div>
                            <h2 class="registrationFeatureTitle mb-1">Subject-Based Practice Tests</h2>
                            <p class="registrationFeatureText small mb-0">
                                Access practice tests, revision materials, and subject-based test banks organised by
                                your lecturers.
                            </p>
                        </div>
                    </li>
                    <li class="d-flex gap-3">
                        <span class="registrationFeatureNumber align-self-start">2</span>
                        <div>
                            <h2 class="registrationFeatureTitle mb-1">Personalised Revision Dashboard</h2>
                            <p class="registrationFeatureText small mb-0">
                                Track progress, identify weak areas, and stay on top of your revision schedule.
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="registrationFormColumn col-12 col-md-6 d-flex flex-column justify-content-center">
                <RegistrationForm />
                <!-- Keep the sign-in route available to people who already have an account. -->
                <p class="registrationSignIn small text-center mt-3 mb-0">
                    Already have a RevisionHub account? <a href="/login">Sign in here</a>
                </p>
            </section>
        </div>
    </div>
</template>
