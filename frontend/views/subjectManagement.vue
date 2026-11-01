<!-- subject management, add edit and archive/restore subjects -->
<script>
import SubjectForm from '../components/forms/subjectForm.vue';
import SubjectTable from '../components/tables/subjectTable.vue';
import { setSubjectActive } from '../services/subjectService';

export default {
    components: {
        SubjectForm,
        SubjectTable,
    },

    data() {
        return {
            statusFilter: 'all',
            message: '',
            errorMessage: '',
            // the row that was just archived or restored, so Undo knows what to put back
            undoSubject: null,
            // true while undo is waiting on php, shows the spinner
            isUndoing: false,
            // timer that hides the undo button after a while
            undoTimer: null,
        };
    },

    // stop the timer if you leave the page before it finishes
    beforeUnmount() {
        clearTimeout(this.undoTimer);
    },

    methods: {
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
        saved() {
            this.errorMessage = '';
            this.undoSubject = null;
            this.message = 'Subject saved';
            this.$refs.table.loadSubjects();
        },

        /**
         * Archive / Restore button, flips whatever it is now
         */
        toggleSubject(subject) {
            this.setStatus(subject, !subject.isActive, true);
        },

        /**
         * Undo in the green box. Button greys out with a spinner till php answers
         */
        async undo() {
            this.isUndoing = true;
            await this.setStatus(this.undoSubject, this.undoSubject.isActive, false);
            this.isUndoing = false;
        },

        /**
         * Does the actual archive or restore for both buttons above
         * canUndo is false when it was an undo, otherwise you could undo the undo forever
         */
        async setStatus(subject, makeActive, canUndo) {
            this.errorMessage = '';

            try {
                await setSubjectActive(subject.subjectID, makeActive);
                this.message = `Subject "${subject.subjectName}" ${makeActive ? 'restored' : 'archived'}`;
                this.undoSubject = canUndo ? subject : null;
                this.$refs.table.loadSubjects();

                // undo only stays for 30 seconds, a new click restarts the clock
                clearTimeout(this.undoTimer);
                if (canUndo) {
                    this.undoTimer = setTimeout(() => {
                        this.undoSubject = null;
                    }, 30000);
                }
            } catch (error) {
                // green box goes so it doesnt look like it worked
                this.message = '';
                this.undoSubject = null;
                this.errorMessage = error.response?.data?.error || 'Unable to update the subject';
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
            v-if="message"
            class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2"
            role="status"
        >
            <span><strong>Success:</strong> {{ message }}</span>
            <!-- aria-label has the name so a screen reader knows which subject its undoing -->
            <button
                v-if="undoSubject"
                type="button"
                class="btn btn-sm btn-outline-primary"
                :aria-label="isUndoing ? 'Undoing' : 'Undo for ' + undoSubject.subjectName"
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

        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
            <div>
                <label for="statusFilter" class="form-label">Show</label>
                <select id="statusFilter" v-model="statusFilter" class="form-select">
                    <option value="all">All subjects</option>
                    <option value="active">Active only</option>
                    <option value="archived">Archived only</option>
                </select>
            </div>
            <button type="button" class="btn btn-primary" @click="addSubject">Add subject</button>
        </div>

        <!-- manage switches on the Actions column, statusFilter is the dropdown above -->
        <SubjectTable ref="table" manage :statusFilter="statusFilter" @edit="editSubject" @toggle="toggleSubject" />
        <SubjectForm ref="form" @saved="saved" />
    </section>
</template>
