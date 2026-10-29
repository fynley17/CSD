<script>
import LoginForm from '../components/forms/loginForm.vue';

// Login page, the one way in for students, lecturers and admins
// the logo and slogan sit at the top of the form on wider screens, and they move under the form on phones
export default {
    components: {
        LoginForm,
    },
};
</script>

<template>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                <LoginForm />

                <!-- logo and slogan, d-md-none only shows them under the form on phones so the form comes first -->
                <div class="d-md-none text-center mt-4">
                    <img src="../assets/images/logoTransparent.png" alt="RevisionHub" class="loginBrandLogo" />
                    <p class="loginBrandSlogan mb-0">Learn - Practise - Progress</p>
                </div>
            </div>
        </div>
    </div>
</template>
