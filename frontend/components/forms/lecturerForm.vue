<!-- Edit Lecturer pop up, the page calls open() with the table row -->
<script>
import PopupDialog from '../common/popupDialog.vue';
import { updateLecturer } from '../../services/administratorService';

export default {
    components: {
        PopupDialog,
    },
    emits: ['saved'],

    data() {
        return {
            userID: null,
            form: { firstName: '', lastName: '', staffID: '', email: '' },
            errorMessage: '',
            isSaving: false,
        };
    },

    methods: {
        /**
         * Fills the boxes in from the table row and shows the pop up
         * @param lecturer the row that Edit was pressed on
         */
        open(lecturer) {
            this.errorMessage = '';
            this.userID = lecturer.userID;
            // copied into form so typing doesnt change the table row behind until its saved
            this.form = {
                firstName: lecturer.firstName,
                lastName: lecturer.lastName,
                staffID: lecturer.staffID,
                email: lecturer.email,
            };
            this.$refs.dialog.open();
        },

        /**
         * Save changes button, php does all the checks (names, staff ID, email already used)
         */
        async save() {
            this.errorMessage = '';
            // disabled while saving so a double click cant send it twice
            this.isSaving = true;
            try {
                await updateLecturer(this.userID, this.form);
                this.$refs.dialog.close();
                this.$emit('saved', 'Changes saved');
            } catch (error) {
                // PHP's message if it sent one i.e. staff ID taken, otherwise a plain one
                this.errorMessage = error.response?.data?.error || 'Unable to save the lecturer';
            } finally {
                this.isSaving = false;
            }
        },
    },
};
</script>

<template>
    <PopupDialog ref="dialog" title="Edit Lecturer">
        <form id="lecturerForm" novalidate @submit.prevent="save">
            <div v-if="errorMessage" class="alert alert-danger" role="alert">
                <strong>There is a problem:</strong> {{ errorMessage }}
            </div>

            <div class="mb-3">
                <label for="lecturerFirstName" class="form-label">First name</label>
                <input
                    id="lecturerFirstName"
                    v-model="form.firstName"
                    type="text"
                    class="form-control"
                    maxlength="50"
                    autocomplete="off"
                />
            </div>

            <div class="mb-3">
                <label for="lecturerLastName" class="form-label">Last name</label>
                <input
                    id="lecturerLastName"
                    v-model="form.lastName"
                    type="text"
                    class="form-control"
                    maxlength="50"
                    autocomplete="off"
                />
            </div>

            <div class="mb-3">
                <label for="lecturerStaffID" class="form-label">Staff ID</label>
                <input
                    id="lecturerStaffID"
                    v-model="form.staffID"
                    type="text"
                    class="form-control"
                    maxlength="8"
                    autocomplete="off"
                />
            </div>

            <div class="mb-3">
                <label for="lecturerEmail" class="form-label">Email</label>
                <input
                    id="lecturerEmail"
                    v-model="form.email"
                    type="email"
                    class="form-control"
                    maxlength="255"
                    autocomplete="off"
                />
            </div>
        </form>

        <!-- form="lecturerForm" links the footer button back up to the form -->
        <template #buttons>
            <button type="submit" form="lecturerForm" class="btn btn-primary" :disabled="isSaving">Save changes</button>
            <button type="button" class="btn btn-outline-primary" @click="$refs.dialog.close()">Cancel</button>
        </template>
    </PopupDialog>
</template>
