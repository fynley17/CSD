<!-- lecturer management, add, edit, disable/enable and delete lecturer accounts -->
<script>
import LecturerForm from '../components/forms/lecturerForm.vue';
import LecturerTable from '../components/tables/lecturerTable.vue';
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import PopupDialog from '../components/common/popupDialog.vue';
import { deleteLecturer, resendSetupLink, setLecturerEnabled } from '../services/administratorService';
import { lecturerStatusOptions } from '../utils/filterOptions';

export default {
    components: {
        LecturerForm,
        LecturerTable,
        PopupDialog,
        TableFilter,
        TableSearch,
    },

    data() {
        return {
            searchText: '',
            statusFilter: 'all',
            statusOptions: lecturerStatusOptions,
            successMessage: '',
            errorMessage: '',
            errorToast: null,
            notificationTimeout: null,
            // who the confirm box is asking about, and whether its a toggle or a delete
            confirmLecturer: null,
            confirmAction: '',
        };
    },

    computed: {
        /**
         * Heading for the confirm box, matches whichever button was pressed
         */
        confirmTitle() {
            if (this.confirmAction === 'delete') {
                return 'Delete Lecturer?';
            }
            return this.confirmLecturer?.isEnabled ? 'Disable account?' : 'Enable account?';
        },

        // text on the button that goes ahead, the title without the question mark
        confirmButtonText() {
            if (this.confirmAction === 'delete') {
                return 'Delete';
            }
            return this.confirmLecturer?.isEnabled ? 'Disable account' : 'Enable account';
        },
    },

    beforeUnmount() {
        clearTimeout(this.notificationTimeout);
    },

    methods: {
        showSuccess(message) {
            this.errorMessage = '';
            if (typeof this.$refs.errorToast?.hidePopover === 'function') {
                this.$refs.errorToast.hidePopover();
            }
            this.successMessage = message;
            if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
            this.notificationTimeout = setTimeout(() => {
                this.successMessage = '';
                this.notificationTimeout = null;
            }, 15000);
        },

        async showError(message) {
            this.errorMessage = message;
            if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
            await this.$nextTick();
            if (
                typeof this.$refs.errorToast?.showPopover === 'function' &&
                !this.$refs.errorToast.matches(':popover-open')
            ) {
                this.$refs.errorToast.showPopover();
            }
            this.notificationTimeout = setTimeout(() => {
                if (typeof this.$refs.errorToast?.hidePopover === 'function') {
                    this.$refs.errorToast.hidePopover();
                }
                this.errorMessage = '';
                this.notificationTimeout = null;
            }, 15000);
        },

        closeError() {
            this.errorMessage = '';
            if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
            this.notificationTimeout = null;
            if (typeof this.$refs.errorToast?.hidePopover === 'function') {
                this.$refs.errorToast.hidePopover();
            }
        },

        // Add lecturer button, empty form with the Role dropdown
        addLecturer() {
            this.$refs.form.open();
        },

        // Edit button on a row, the form fills itself in from it
        editLecturer(lecturer) {
            this.$refs.form.open(lecturer);
        },

        /**
         * Resend link on someone still waiting to set their password, sends them a fresh email
         */
        async resendLink(lecturer) {
            this.errorMessage = '';

            try {
                await resendSetupLink(lecturer.userID);
                this.showSuccess(`Setup link sent to ${lecturer.name} successfully.`);
            } catch (error) {
                this.successMessage = '';
                this.showError(error.response?.data?.error || 'Unable to resend the setup link.');
            }
        },

        /**
         * Form worked, so fresh rows and a green box (red if the email didn't go)
         * staffID only comes from Add lecturer, a new person hasn't logged in so they'd be on the last page
         * so the search box gets their staff ID to show them straight away
         */
        async saved(message, staffID, emailSent) {
            // only Add sends emailSent, false = account is made but the email didn't go
            // green would say success on something the admin still has to fix, so its the red box
            if (emailSent === false) {
                this.successMessage = '';
                this.showError(message);
            } else {
                this.errorMessage = '';
                this.showSuccess(message);
            }
            await this.$refs.table.loadLecturers();
            if (staffID) {
                // a Disabled filter would hide the new person, so back to all first
                this.statusFilter = 'all';
                await this.$nextTick();
                this.searchText = staffID;
            }
        },

        /**
         * Disable/Enable and Delete both ask first, house style wants a pop up for any disable or delete
         */
        askFirst(lecturer, action) {
            this.confirmLecturer = lecturer;
            this.confirmAction = action;
            this.$refs.confirmDialog.open();
        },

        // the red or blue button inside the confirm box
        async confirmChange() {
            this.$refs.confirmDialog.close();
            if (this.confirmAction === 'delete') {
                await this.removeLecturer(this.confirmLecturer);
            } else {
                await this.setStatus(this.confirmLecturer, !this.confirmLecturer.isEnabled);
            }
        },

        /**
         * Turns the account off or on.
         */
        async setStatus(lecturer, isEnabled) {
            this.errorMessage = '';

            try {
                await setLecturerEnabled(lecturer.userID, isEnabled);
                this.showSuccess(isEnabled ? 'Lecturer enabled successfully.' : 'Lecturer disabled successfully.');
                this.$refs.table.loadLecturers();
            } catch (error) {
                this.successMessage = '';
                const message = `Unable to ${isEnabled ? 'enable' : 'disable'} the lecturer.`;
                this.showError(error.response?.data?.error || message);
            }
        },

        /**
         * Delete for real.
         */
        async removeLecturer(lecturer) {
            this.errorMessage = '';

            try {
                await deleteLecturer(lecturer.userID);
                this.showSuccess('Lecturer deleted successfully.');
                this.$refs.table.loadLecturers();
            } catch (error) {
                this.successMessage = '';
                this.showError(error.response?.data?.error || 'Unable to delete the lecturer.');
            }
        },
    },
};
</script>

<template>
    <section class="container py-4 adminPage">
        <h1>Lecturer Management</h1>
        <p>Add, edit, disable and delete lecturer accounts</p>

        <div
            v-if="successMessage"
            class="toastMessage successMessage d-flex flex-wrap align-items-center gap-2"
            role="status"
        >
            <span aria-hidden="true">✓</span>
            <span>{{ successMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="successMessage = ''">
                ×
            </button>
        </div>
        <div
            v-if="errorMessage"
            ref="errorToast"
            popover="manual"
            class="toastMessage errorMessage"
            role="alert"
            aria-live="assertive"
        >
            <span aria-hidden="true">!</span>
            <span>{{ errorMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeError">×</button>
        </div>

        <div class="tableToolbar">
            <div class="tableToolbarControls">
                <TableSearch id="lecturerSearch" v-model="searchText" placeholder="Name, staff ID or email" />
                <TableFilter id="lecturerStatusFilter" v-model="statusFilter" label="Status" :options="statusOptions" />
            </div>
            <div class="tableToolbarActions">
                <button type="button" class="btn btn-primary" @click="addLecturer">
                    <span aria-hidden="true">+</span> Add lecturer
                </button>
            </div>
        </div>

        <LecturerTable
            ref="table"
            manage
            :search="searchText"
            :statusFilter="statusFilter"
            @edit="editLecturer"
            @toggle="askFirst($event, 'toggle')"
            @delete="askFirst($event, 'delete')"
            @resend="resendLink"
        />

        <!-- one confirm box for disable, enable and delete, the words change with the button pressed -->
        <PopupDialog ref="confirmDialog" :title="confirmTitle">
            <div v-if="confirmLecturer" class="text-center">
                <i class="bi bi-exclamation-circle fs-1" aria-hidden="true"></i>
                <p class="fw-bold mb-2">{{ confirmLecturer.name }}</p>
                <p v-if="confirmAction === 'delete'" class="mb-0">
                    Their account is removed and can't be brought back. Their tests are kept and moved to you
                </p>
                <p v-else-if="confirmLecturer.isEnabled" class="mb-0">They won't be able to log in until enabled</p>
                <p v-else class="mb-0">They'll be able to log in again</p>
            </div>
            <template #buttons>
                <!-- same width side by side like the cohort pop-ups, stacked on a phone -->
                <div class="d-grid d-sm-flex gap-2 w-100">
                    <button type="button" class="btn btn-outline-primary w-100" @click="$refs.confirmDialog.close()">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn w-100"
                        :class="confirmAction === 'delete' ? 'btn-danger' : 'btn-primary'"
                        @click="confirmChange"
                    >
                        {{ confirmButtonText }}
                    </button>
                </div>
            </template>
        </PopupDialog>
        <LecturerForm ref="form" @saved="saved" @error="showError" />
    </section>
</template>

<style scoped>
.toastMessage {
    position: fixed;
    z-index: 1100;
    top: calc(var(--navHeight) + var(--spaceLg));
    right: var(--spaceLg);
    width: max-content;
    max-width: min(450px, calc(100vw - 2 * var(--spaceLg)));
    padding: var(--spaceMd) var(--spaceLg);
    border: 2px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font-weight: 600;
}

.toastClose {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: inherit;
    font-size: var(--fontSizeH3);
    line-height: 1;
    cursor: pointer;
}

.successMessage {
    border-color: var(--colorPrimary);
}

.errorMessage {
    border-color: var(--colorError);
    color: var(--colorError);
}

@media (max-width: 639px) {
    .toastMessage {
        top: calc(var(--navHeight) + var(--spaceMd));
        right: var(--spaceMd);
    }

    .errorMessage:popover-open {
        inset: calc(var(--navHeight) + var(--spaceMd)) var(--spaceMd) auto auto;
    }
}

.errorMessage:popover-open {
    position: fixed;
    inset: calc(var(--navHeight) + var(--spaceLg)) var(--spaceLg) auto auto;
}
</style>
