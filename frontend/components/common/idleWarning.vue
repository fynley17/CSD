<script>
import PopupDialog from './popupDialog.vue';
import { setIdleHandlers } from '../../services/idleTimer';
import { getCurrentUser } from '../../services/authService';

// "Are you still there?" pop-up, sits in app.vue so it works on every page
// idleTimer.js opens it after 28 minutes with no requests, 2 minutes before PHP logs them out
export default {
    components: {
        PopupDialog,
    },
    data() {
        return {
            isWarningOpen: false,
        };
    },
    mounted() {
        setIdleHandlers(this.showWarning, this.handleTimeout);
    },
    methods: {
        /**
         * Opens the warning at 28 minutes
         */
        showWarning() {
            this.isWarningOpen = true;
            this.$refs.idlePopup.open();
        },

        /**
         * Runs however the pop-up gets shut, the button, the X or Esc all count as still being here
         */
        warningClosed() {
            this.isWarningOpen = false;
            this.checkStillLoggedIn();
        },

        /**
         * Runs at 30 minutes if nobody answered the warning
         */
        handleTimeout() {
            if (this.isWarningOpen) {
                // closing it runs warningClosed(), which does the check, so it isn't done twice
                this.$refs.idlePopup.close();
            } else {
                // the warning isn't open, i.e. it was closed but PHP couldn't be reached, so check again now
                this.checkStillLoggedIn();
            }
        },

        /**
         * Asks PHP who's logged in, the request itself starts PHP's 30 minutes again
         * if PHP has already logged them out, they go to the login page with a message saying why
         */
        async checkStillLoggedIn() {
            let user;

            try {
                user = await getCurrentUser();
            } catch {
                // PHP couldn't be reached, so it's treated the same as being logged out
                user = null;
            }

            if (user === null) {
                this.$router.push({ name: 'login', query: { signedOut: 'idle' } });
            }
        },
    },
};
</script>

<template>
    <PopupDialog ref="idlePopup" title="Are you still there?" @close="warningClosed">
        <p class="mb-0">You will be signed out in 2 minutes because there has been no activity for 28 minutes</p>
        <template #buttons>
            <button type="button" class="headerButton loginButton w-100" @click="$refs.idlePopup.close()">
                Stay logged in
            </button>
        </template>
    </PopupDialog>
</template>
