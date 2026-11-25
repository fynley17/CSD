<!-- subject management, add edit and archive/restore subjects -->
<script>
import SubjectForm from '../components/forms/subjectForm.vue';
import SubjectTable from '../components/tables/subjectTable.vue';
import TableFilter from '../components/tables/tableFilter.vue';
import TableSearch from '../components/tables/tableSearch.vue';
import PopupDialog from '../components/common/popupDialog.vue';
import { setSubjectActive } from '../services/subjectService';
import { subjectStatusOptions } from '../utils/filterOptions';

export default {
    components: {
        SubjectForm,
        SubjectTable,
        TableFilter,
        TableSearch,
        PopupDialog,
    },

    data() {
        return {
            searchText: '',
            statusFilter: 'all',
            statusOptions: subjectStatusOptions,
            successMessage: '',
            errorMessage: '',
            errorToast: null,
            notificationTimeout: null,
            // the row the confirm box is asking about
            confirmSubject: null,
            // subject that was just archived or restored so Undo knows which one
            undoSubject: null,
        };
    },

    // stop the timer if you leave the page before it finishes
    beforeUnmount() {
        clearTimeout(this.notificationTimeout);
    },

    methods: {
        showSuccess(message, undoSubject = null) {
            this.errorMessage = '';
            this.undoSubject = undoSubject;
            if (typeof this.$refs.errorToast?.hidePopover === 'function') {
                this.$refs.errorToast.hidePopover();
            }
            this.successMessage = message;
            if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
            this.notificationTimeout = setTimeout(() => {
                this.successMessage = '';
                this.notificationTimeout = null;
            }, 20000);
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
            }, 20000);
        },

        closeError() {
            this.errorMessage = '';
            if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
            this.notificationTimeout = null;
            if (typeof this.$refs.errorToast?.hidePopover === 'function') {
                this.$refs.errorToast.hidePopover();
            }
        },

        /**
         * Add button, form opens blank
         */
        addSubject() {
            this.$refs.form.open();
        },

        // Edit button on a row, gives that row to the form
        editSubject(subject) {
            this.$refs.form.open(subject);
        },

        /**
         * Form says saved when it worked, so refresh the table and show the green box
         */
        saved(message) {
            this.errorMessage = '';
            this.showSuccess(message);
            this.$refs.table.loadSubjects();
        },

        /**
         * Archive / Restore button, opens the confirm box first
         */
        toggleSubject(subject) {
            this.confirmSubject = subject;
            this.$refs.confirmDialog.open();
        },

        // the Archive / Restore button inside the confirm box
        confirmChange() {
            this.$refs.confirmDialog.close();
            this.setStatus(this.confirmSubject, !this.confirmSubject.isActive, true);
        },

        /**
         * Does the actual archive or restore for both buttons above
         */
        async setStatus(subject, makeActive, canUndo = false) {
            this.errorMessage = '';

            try {
                await setSubjectActive(subject.subjectID, makeActive);
                this.showSuccess(
                    makeActive ? 'Subject restored successfully.' : 'Subject archived successfully.',
                    canUndo ? subject : null,
                );
                this.$refs.table.loadSubjects();
            } catch (error) {
                // green box goes so it doesnt look like it worked
                this.successMessage = '';
                const message = `Unable to ${makeActive ? 'restore' : 'archive'} the subject.`;
                this.showError(error.response?.data?.error || message);
            }
        },

        // puts it back, false so theres no Undo on the undo
        undo() {
            const subject = this.undoSubject;
            this.undoSubject = null;
            this.setStatus(subject, subject.isActive, false);
        },
    },
};
</script>

<template>
    <section class="container py-4 adminPage">
        <h1>Subject Management</h1>
        <p>Create, edit and archive subjects. Subjects are never deleted</p>

        <div v-if="successMessage" class="toastMessage successMessage d-flex align-items-center gap-2" role="status">
            <span aria-hidden="true">✓</span>
            <span>{{ successMessage }}</span>
            <button
                v-if="undoSubject"
                type="button"
                class="btn btn-sm btn-outline-primary toastUndo"
                :aria-label="'Undo for ' + undoSubject.subjectName"
                @click="undo"
            >
                Undo
            </button>
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
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ errorMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="closeError">×</button>
        </div>

        <div class="tableToolbar">
            <div class="tableToolbarControls">
                <TableSearch id="subjectSearch" v-model="searchText" placeholder="Subject, cohort or lecturer" />
                <TableFilter id="subjectStatusFilter" v-model="statusFilter" label="Status" :options="statusOptions" />
            </div>
            <div class="tableToolbarActions">
                <button type="button" class="btn btn-primary" @click="addSubject">
                    <span aria-hidden="true">+</span> Add subject
                </button>
            </div>
        </div>

        <!-- manage switches on the Actions column, statusFilter is the dropdown above -->
        <SubjectTable
            ref="table"
            manage
            :search="searchText"
            :statusFilter="statusFilter"
            @edit="editSubject"
            @toggle="toggleSubject"
        />
        <!-- confirm box for Archive / Restore, flowchart has a Confirm change? step -->
        <PopupDialog ref="confirmDialog" :title="confirmSubject?.isActive ? 'Archive subject' : 'Restore subject'">
            <p v-if="confirmSubject?.isActive" class="mb-0">
                Archive "{{ confirmSubject.subjectName }}"? It is hidden from new tests and past results are kept
            </p>
            <p v-else-if="confirmSubject" class="mb-0">
                Restore "{{ confirmSubject.subjectName }}"? It can be used for new tests again
            </p>
            <template #buttons>
                <button type="button" class="btn btn-outline-primary flex-fill" @click="$refs.confirmDialog.close()">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary flex-fill" @click="confirmChange">
                    {{ confirmSubject?.isActive ? 'Archive' : 'Restore' }}
                </button>
            </template>
        </PopupDialog>
        <SubjectForm ref="form" @saved="saved" @error="showError" />
    </section>
</template>
