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
            message: '',
            errorMessage: '',
            // the row that was just disabled or enabled, so Undo knows who to put back
            undoLecturer: null,
            isUndoing: false,
            undoTimer: null,
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

    // stops the undo timer if you leave the page early
    beforeUnmount() {
        clearTimeout(this.undoTimer);
    },

    methods: {
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
            this.undoLecturer = null;

            try {
                await resendSetupLink(lecturer.userID);
                this.message = 'A new link has been sent to ' + lecturer.name;
            } catch (error) {
                this.message = '';
                this.errorMessage = error.response?.data?.error || 'Unable to send a new link';
            }
        },

        /**
         * Form worked, so green box and fresh rows
         * staffID only comes from Add lecturer, a new person hasn't logged in so they'd be on the last page
         * so the search box gets their staff ID to show them straight away
         */
        async saved(message, staffID) {
            this.errorMessage = '';
            this.undoLecturer = null;
            this.message = message;
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
                await this.setStatus(this.confirmLecturer, !this.confirmLecturer.isEnabled, true);
            }
        },

        /**
         * Undo in the green box, spinner till php answers
         */
        async undo() {
            this.isUndoing = true;
            await this.setStatus(this.undoLecturer, this.undoLecturer.isEnabled, false);
            this.isUndoing = false;
        },

        /**
         * Turns the account off or on. canUndo is false for an undo so you cant undo the undo
         */
        async setStatus(lecturer, isEnabled, canUndo) {
            this.errorMessage = '';

            try {
                await setLecturerEnabled(lecturer.userID, isEnabled);
                this.message = 'Status updated';
                this.undoLecturer = canUndo ? lecturer : null;
                this.$refs.table.loadLecturers();

                // undo goes after 30 secs, the message itself stays
                clearTimeout(this.undoTimer);
                if (canUndo) {
                    this.undoTimer = setTimeout(() => {
                        this.undoLecturer = null;
                    }, 30000);
                }
            } catch (error) {
                this.message = '';
                this.undoLecturer = null;
                this.errorMessage = error.response?.data?.error || 'Unable to update the lecturer';
            }
        },

        /**
         * Delete for real, no undo for this one as the account is gone
         */
        async removeLecturer(lecturer) {
            this.errorMessage = '';
            this.undoLecturer = null;

            try {
                await deleteLecturer(lecturer.userID);
                this.message = 'Lecturer deleted';
                this.$refs.table.loadLecturers();
            } catch (error) {
                this.message = '';
                this.errorMessage = error.response?.data?.error || 'Unable to delete the lecturer';
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
            v-if="message"
            class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2"
            role="status"
        >
            <span><strong>Success:</strong> {{ message }}</span>
            <button
                v-if="undoLecturer"
                type="button"
                class="btn btn-sm btn-outline-primary"
                :aria-label="isUndoing ? 'Undoing' : 'Undo for ' + undoLecturer.name"
                :disabled="isUndoing"
                @click="undo"
            >
                <span v-if="isUndoing" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                {{ isUndoing ? 'Undoing' : 'Undo' }}
            </button>
        </div>
        <div v-if="errorMessage" class="alert alert-danger" role="alert">
            <strong>There is a problem:</strong> {{ errorMessage }}
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
                <button type="button" class="btn btn-outline-primary flex-fill" @click="$refs.confirmDialog.close()">
                    Cancel
                </button>
                <button
                    type="button"
                    class="btn flex-fill"
                    :class="confirmAction === 'delete' ? 'btn-danger' : 'btn-primary'"
                    @click="confirmChange"
                >
                    {{ confirmButtonText }}
                </button>
            </template>
        </PopupDialog>
        <LecturerForm ref="form" @saved="saved" />
    </section>
</template>
