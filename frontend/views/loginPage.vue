<script>
import LoginForm from '../components/forms/loginForm.vue';

// Login page, the one way in for students, lecturers and admins
// the logo sits in the shared header above and this page centres the form
export default {
    components: {
        LoginForm,
    },
};
</script>

<template>
    <div class="container py-5">
        <!-- keeps the form narrow and in the middle, full width on phones -->
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <LoginForm />
            </div>
        </div>
    </div>
</template>
