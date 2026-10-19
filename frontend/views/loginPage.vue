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
        <LoginForm />
    </div>
</template>
