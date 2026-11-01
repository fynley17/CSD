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
        };
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
            this.message = 'Subject saved';
            this.$refs.table.loadSubjects();
        },

        /**
         * Archive / Restore button
         */
        async toggleSubject(subject) {
            // clear the old boxes first or the last message hangs around
            this.message = '';
            this.errorMessage = '';
            // flip it, active becomes archived and the other way round
            const makeActive = !subject.isActive;

            try {
                await setSubjectActive(subject.subjectID, makeActive);
                this.message = makeActive ? 'Subject restored' : 'Subject archived';
                this.$refs.table.loadSubjects();
            } catch (error) {
                this.errorMessage = error.response?.data?.error || 'Unable to update the subject';
            }
        },
    },
};
</script>

<template>
    <section class="container py-4 subjectManagement">
        <h1>Subject Management</h1>
        <p>Create, edit and archive subjects. Subjects are never deleted</p>

        <div v-if="message" class="alert alert-success" role="status"><strong>Success:</strong> {{ message }}</div>
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
