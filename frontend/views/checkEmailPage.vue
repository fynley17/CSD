<script>
import CheckEmailForm from '../components/forms/checkEmailForm.vue';

// Check your email page, shown after registering or after trying to login before verifying
// the logo sits in the shared header above and this page centres the card, same as the login page
export default {
    components: {
        CheckEmailForm,
    },
};
</script>

<template>
    <div class="container py-5">
        <!-- keeps the card narrow and in the middle, full width on phones -->
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <CheckEmailForm />
            </div>
        </div>
    </div>
</template>
