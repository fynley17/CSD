<script>
import LoginForm from '../components/forms/loginForm.vue';

// Login page, the one way in for students, lecturers and admins
// the form sits next to the logo and slogan on wider screens, and they move under the form on phones
export default {
    components: {
        LoginForm,
    },
};
</script>

<template>
    <div class="container py-5">
        <div class="row justify-content-center align-items-stretch g-4">
            <!-- logo and slogan, order-last moves them under the form on phones so the form comes first -->
            <div class="col-12 col-md-6 col-lg-5 order-last order-md-first">
                <div class="card surfaceCard loginBrand h-100">
                    <div class="card-body p-4 p-md-5 d-flex flex-column justify-content-center">
                        <img src="../assets/images/Logo.png" alt="RevisionHub" class="loginBrandLogo" />
                        <p class="loginBrandSlogan mb-0">Learn, Practise, Progress</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-5">
                <LoginForm />
            </div>
        </div>
    </div>
</template>
