<script>
import { saveBackupCodes } from '../../services/authService';

// Backup codes card, shown once after an MFA login when the account has no backup codes left
// each code logs in once without the email, PHP only keeps hashes so this is the only time they're shown
// a step in the login card and not a pop-up, so Esc or the X can't skip it
// PHP only saves the codes once Continue works, so a refresh before then means a new set next login
export default {
    props: {
        // the 8 new codes from PHP, i.e. K7QM-2XPA
        codes: {
            type: Array,
            required: true,
        },
    },
    emits: ['done'],
    data() {
        return {
            hasSaved: false,
            // true once Copy codes or Download codes has worked, Continue needs one of them
            hasCopiedOrDownloaded: false,
            // filled in when Continue is pressed without ticking the box
            fieldError: '',
            // set when copying fails or PHP couldn't save the codes
            serverError: '',
            successMessage: '',
            isSaving: false,
        };
    },
    computed: {
        // the same text goes on the clipboard and in the downloaded file
        codesText() {
            return (
                'RevisionHub backup codes\nEach code works once. Keep them somewhere safe.\n\n' + this.codes.join('\n')
            );
        },
    },
    mounted() {
        // the heading takes focus so a screen reader starts at the top of the new card
        this.$refs.heading.focus();
    },
    methods: {
        // Copy codes button, puts all 8 on the clipboard to paste into a password manager
        async copyCodes() {
            this.clearMessages();

            try {
                await navigator.clipboard.writeText(this.codesText);
                this.hasCopiedOrDownloaded = true;
                this.successMessage = 'Backup codes copied';
            } catch {
                // i.e. the browser has clipboard access turned off
                this.serverError = 'Your browser did not let the codes be copied. Use Download codes instead';
            }
            await this.focusMessage();
        },

        // Download codes button, makes a .txt file in the browser so nothing else gets sent to the server
        async downloadCodes() {
            this.clearMessages();

            const file = new Blob([this.codesText], { type: 'text/plain' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(file);
            link.download = 'revisionhub-backup-codes.txt';
            link.click();
            // frees the file from memory once the download has started
            URL.revokeObjectURL(link.href);

            this.hasCopiedOrDownloaded = true;
            this.successMessage = 'Backup codes downloaded';
            await this.focusMessage();
        },

        // Continue needs the codes copied or downloaded and the box ticked, they can't be shown again after this
        async finish() {
            this.clearMessages();

            if (!this.hasCopiedOrDownloaded) {
                this.serverError = 'Copy or download your backup codes before you continue';
                await this.focusMessage();
                return;
            }
            if (!this.hasSaved) {
                this.fieldError = 'Tick the box to confirm you have saved your backup codes';
                await this.focusMessage();
                return;
            }

            this.isSaving = true;
            try {
                await saveBackupCodes();
                this.$emit('done');
            } catch (error) {
                this.serverError = error.response?.data?.error || 'Unable to save your backup codes. Try again';
                await this.focusMessage();
            } finally {
                this.isSaving = false;
            }
        },

        clearMessages() {
            this.fieldError = '';
            this.serverError = '';
            this.successMessage = '';
        },

        // moving focus to the message means a screen reader reads it out straight away
        async focusMessage() {
            await this.$nextTick();
            this.$refs.messageBox?.focus();
        },
    },
};
</script>

<template>
    <div class="card surfaceCard loginForm">
        <div class="card-body p-4 p-md-5">
            <h1 ref="heading" class="mb-3" tabindex="-1">Save your backup codes now</h1>

            <p>
                If you can't get the security code email, you can login with one of these codes instead. Each code only
                works once.
            </p>

            <div class="alert alert-info d-flex gap-2" role="status">
                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                <p class="mb-0">
                    <strong>Information:</strong> You will only see these codes once. Save them in more than one place,
                    for example a password manager and a printed copy
                </p>
            </div>

            <ul class="list-unstyled row row-cols-2 g-2 fw-bold fs-5 mb-4" aria-label="Backup codes">
                <li v-for="code in codes" :key="code" class="col">{{ code }}</li>
            </ul>

            <!-- one message box for every reply, it stays until the next button press -->
            <div
                v-if="fieldError || serverError"
                ref="messageBox"
                class="alert alert-danger d-flex gap-2"
                role="alert"
                tabindex="-1"
            >
                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <div v-if="fieldError">
                    <h2 class="messageTitle">There is a problem</h2>
                    <ul class="messageList mb-0">
                        <li>
                            <a href="#savedCodes">{{ fieldError }}</a>
                        </li>
                    </ul>
                </div>
                <p v-else class="mb-0"><strong>There is a problem:</strong> {{ serverError }}</p>
            </div>
            <div
                v-else-if="successMessage"
                ref="messageBox"
                class="alert successMessage d-flex gap-2"
                role="status"
                tabindex="-1"
            >
                <svg class="messageIcon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="8 12 11 15 16 9" />
                </svg>
                <p class="mb-0"><strong>Success:</strong> {{ successMessage }}</p>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 mb-4">
                <button type="button" class="headerButton registerButton flex-fill" @click="copyCodes">
                    Copy codes
                </button>
                <button type="button" class="headerButton registerButton flex-fill" @click="downloadCodes">
                    Download codes
                </button>
            </div>

            <!-- novalidate turns off the browser's own pop-ups so only the house style errors show -->
            <form novalidate @submit.prevent="finish">
                <p v-if="fieldError" id="savedCodesError" class="fieldError">Error: {{ fieldError }}</p>
                <div class="form-check mb-4">
                    <input
                        id="savedCodes"
                        v-model="hasSaved"
                        type="checkbox"
                        class="form-check-input"
                        :class="{ inputError: fieldError }"
                        :aria-invalid="fieldError ? 'true' : 'false'"
                        :aria-describedby="fieldError ? 'savedCodesError' : undefined"
                    />
                    <label for="savedCodes" class="form-check-label">I have saved these codes somewhere safe</label>
                </div>

                <button type="submit" class="headerButton loginButton w-100" :disabled="isSaving">Continue</button>
            </form>
        </div>
    </div>
</template>
