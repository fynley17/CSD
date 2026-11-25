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
            notificationTimeout: null,
            // the row the confirm box is asking about
            confirmSubject: null,
        };
    },

    // stop the timer if you leave the page before it finishes
    beforeUnmount() {
        clearTimeout(this.notificationTimeout);
    },

    methods: {
        showSuccess(message) {
            this.successMessage = message;
            if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
            this.notificationTimeout = setTimeout(() => {
                this.successMessage = '';
                this.notificationTimeout = null;
            }, 15000);
        },

        showError(message) {
            this.errorMessage = message;
            if (this.notificationTimeout) clearTimeout(this.notificationTimeout);
            this.notificationTimeout = setTimeout(() => {
                this.errorMessage = '';
                this.notificationTimeout = null;
            }, 15000);
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
        async setStatus(subject, makeActive) {
            this.errorMessage = '';

            try {
                await setSubjectActive(subject.subjectID, makeActive);
                this.showSuccess(makeActive ? 'Subject restored successfully.' : 'Subject archived successfully.');
                this.$refs.table.loadSubjects();
            } catch (error) {
                // green box goes so it doesnt look like it worked
                this.successMessage = '';
                const message = `Unable to ${makeActive ? 'restore' : 'archive'} the subject.`;
                this.showError(error.response?.data?.error || message);
            }
        },
    },
};
</script>

<template>
    <section class="container py-4 adminPage">
        <h1>Subject Management</h1>
        <p>Create, edit and archive subjects. Subjects are never deleted</p>

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
        <div v-if="errorMessage" class="toastMessage errorMessage" role="alert" aria-live="assertive">
            <span aria-hidden="true">!</span>
            <span>{{ errorMessage }}</span>
            <button type="button" class="toastClose" aria-label="Close notification" @click="errorMessage = ''">
                ×
            </button>
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
    font-size: 1.25rem;
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
}
</style>
