<!-- Add Lecturer and Edit Lecturer pop up, the page calls open() with a table row to edit or nothing to add -->
<script>
import PopupDialog from '../common/popupDialog.vue';
import { createLecturer, updateLecturer } from '../../services/administratorService';

export default {
    components: {
        PopupDialog,
    },
    emits: ['saved'],

    data() {
        return {
            // null means Add Lecturer, otherwise the ID of the one being edited
            userID: null,
            form: { firstName: '', lastName: '', staffID: '', email: '', role: 'lecturer' },
            errorMessage: '',
            isSaving: false,
            // true after the first Save press on an Administrator, the second press really saves
            confirmingAdmin: false,
        };
    },

    computed: {
        isAdding() {
            return this.userID === null;
        },

        title() {
            return this.isAdding ? 'Add Lecturer' : 'Edit Lecturer';
        },

        saveText() {
            if (!this.isAdding) {
                return 'Save changes';
            }
            if (this.confirmingAdmin) {
                return 'Yes, create administrator';
            }
            return 'Add lecturer';
        },
    },

    methods: {
        /**
         * Shows the pop up. With a row it's Edit and the boxes are filled in, with nothing it's Add
         * @param lecturer the row that Edit was pressed on, or null for Add lecturer
         */
        open(lecturer = null) {
            this.errorMessage = '';
            this.confirmingAdmin = false;

            if (lecturer === null) {
                this.userID = null;
                this.form = { firstName: '', lastName: '', staffID: '', email: '', role: 'lecturer' };
            } else {
                this.userID = lecturer.userID;
                // copied into form so typing doesnt change the table row behind until its saved
                this.form = {
                    firstName: lecturer.firstName,
                    lastName: lecturer.lastName,
                    staffID: lecturer.staffID,
                    email: lecturer.email,
                    role: lecturer.role || 'lecturer',
                };
            }
            this.$refs.dialog.open();
        },

        /**
         * Save button, php does all the checks (names, staff ID, email already used, role)
         */
        async save() {
            this.errorMessage = '';

            // an admin can do a lot, so the first press only shows the warning
            if (this.isAdding && this.form.role === 'admin' && !this.confirmingAdmin) {
                this.confirmingAdmin = true;
                return;
            }

            // disabled while saving so a double click cant send it twice
            this.isSaving = true;
            try {
                if (this.isAdding) {
                    const result = await createLecturer(this.form);
                    this.$refs.dialog.close();
                    // php says whether the email went, so that message goes to the page
                    // the staff ID goes too so the page can find the new row for the admin
                    this.$emit('saved', result.message, this.form.staffID.trim());
                } else {
                    // edit doesn't change the role, so only the four boxes go
                    const { firstName, lastName, staffID, email } = this.form;
                    await updateLecturer(this.userID, { firstName, lastName, staffID, email });
                    this.$refs.dialog.close();
                    this.$emit('saved', 'Changes saved');
                }
            } catch (error) {
                // PHP's message if it sent one i.e. staff ID taken, otherwise a plain one
                this.errorMessage = error.response?.data?.error || 'Unable to save the lecturer';
                this.confirmingAdmin = false;
            } finally {
                this.isSaving = false;
            }
        },
    },
};
</script>

<template>
    <PopupDialog ref="dialog" :title="title">
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
                    aria-describedby="lecturerStaffIDHint"
                />
                <div id="lecturerStaffIDHint" class="form-text">They log in with this, for example L1001</div>
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
                    :aria-describedby="isAdding ? 'lecturerEmailHint' : null"
                />
                <div v-if="isAdding" id="lecturerEmailHint" class="form-text">
                    They're emailed a link to set their own password, so you never see it
                </div>
            </div>

            <!-- role is only picked when adding, an admin account can't be edited on this page
                 changing it after the warning showed means they read the warning again -->
            <div v-if="isAdding" class="mb-3">
                <label for="lecturerRole" class="form-label">Role</label>
                <select
                    id="lecturerRole"
                    v-model="form.role"
                    class="form-select"
                    :aria-describedby="form.role === 'admin' ? 'lecturerRoleHint' : null"
                    @change="confirmingAdmin = false"
                >
                    <option value="lecturer">Lecturer</option>
                    <option value="admin">Administrator</option>
                </select>
                <!-- plain text not a hover tooltip, so it works on a phone and with a keyboard -->
                <div v-if="form.role === 'admin'" id="lecturerRoleHint" class="form-text">
                    Administrators can do everything a lecturer can, and manage lecturers, subjects and cohorts
                </div>
            </div>

            <div v-if="confirmingAdmin" class="alert alert-warning mb-3" role="alert">
                <strong>Check before you carry on:</strong> {{ form.firstName }} {{ form.lastName }} will be an
                administrator. Administrator accounts can't be edited, disabled or deleted on this page
            </div>
        </form>

        <!-- form="lecturerForm" links the footer button back up to the form -->
        <template #buttons>
            <button type="submit" form="lecturerForm" class="btn btn-primary" :disabled="isSaving">
                {{ saveText }}
            </button>
            <button type="button" class="btn btn-outline-primary" @click="$refs.dialog.close()">Cancel</button>
        </template>
    </PopupDialog>
</template>
