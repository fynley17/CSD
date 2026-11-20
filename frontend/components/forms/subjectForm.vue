<!-- add and edit subject pop up, the page calls open() to show it -->
<script>
import PopupDialog from '../common/popupDialog.vue';
import { createSubject, getCohorts, getLecturerOptions, updateSubject } from '../../services/subjectService';

export default {
    components: {
        PopupDialog,
    },
    emits: ['saved'],

    data() {
        return {
            // null means a new subject, otherwise its the one being edited
            subjectID: null,
            form: { subjectName: '', cohortID: '', lecturerIDs: [] },
            cohorts: [],
            lecturers: [],
            errorMessage: '',
            isSaving: false,
        };
    },

    computed: {
        /**
         * Heading changes depending on add or edit
         */
        title() {
            return this.subjectID === null ? 'Add Subject' : 'Edit Subject';
        },
    },

    methods: {
        /**
         * Opens the popup. Blank for a new subject, or filled in from the table row if editing
         * @param subject row from the table, leave it out for a new one
         */
        async open(subject = null) {
            this.errorMessage = '';
            this.subjectID = subject === null ? null : subject.subjectID;
            this.form = {
                subjectName: subject === null ? '' : subject.subjectName,
                // a subject can be in no cohort, null would show a blank box
                cohortID: subject === null || subject.cohortID === null ? '' : subject.cohortID,
                // slice makes a copy so ticking boxes doesnt change the table row behind
                lecturerIDs: subject === null ? [] : subject.lecturerIDs.slice(),
            };
            this.$refs.dialog.open();

            // get the lists fresh each time in case someone added a lecturer or cohort meanwhile
            try {
                // ask for both at once, the answers come back in the order asked
                const results = await Promise.all([getCohorts(), getLecturerOptions()]);
                this.cohorts = results[0];
                this.lecturers = results[1];

                // a disabled cohort isnt in the list, so add the subjects own one back or the box shows blank
                const hasCohort = subject !== null && subject.cohortID !== null;
                if (hasCohort && !this.cohorts.some((cohort) => cohort.cohortID === subject.cohortID)) {
                    this.cohorts.push({ cohortID: subject.cohortID, cohortName: subject.cohortName });
                }
            } catch (error) {
                this.errorMessage = error.response?.data?.error || 'Unable to load the cohorts and lecturers';
            }
        },

        /**
         * Save button. Only checks the cohort here, php does the name check
         */
        async save() {
            this.errorMessage = '';
            if (this.form.cohortID === '') {
                this.errorMessage = 'Choose a cohort';
                return;
            }

            // button is disabled while this is true so a double click cant save twice
            this.isSaving = true;
            try {
                if (this.subjectID === null) {
                    await createSubject(this.form);
                } else {
                    await updateSubject(this.subjectID, this.form);
                }
                this.$refs.dialog.close();
                this.$emit('saved', this.subjectID === null ? 'Subject added' : 'Changes saved');
            } catch (error) {
                // message from PHP if there is one (like name already used in that cohort) or a generic one
                this.errorMessage = error.response?.data?.error || 'Unable to save the subject';
            } finally {
                this.isSaving = false;
            }
        },
    },
};
</script>

<template>
    <PopupDialog ref="dialog" :title="title">
        <form id="subjectForm" novalidate @submit.prevent="save">
            <div v-if="errorMessage" class="alert alert-danger" role="alert">
                <strong>There is a problem:</strong> {{ errorMessage }}
            </div>

            <div class="mb-3">
                <label for="subjectName" class="form-label">Subject name</label>
                <input id="subjectName" v-model="form.subjectName" type="text" class="form-control" maxlength="100" />
            </div>

            <div class="mb-3">
                <label for="subjectCohort" class="form-label">Cohort</label>
                <select id="subjectCohort" v-model="form.cohortID" class="form-select">
                    <option value="">Select a cohort</option>
                    <option v-for="cohort in cohorts" :key="cohort.cohortID" :value="cohort.cohortID">
                        {{ cohort.cohortName }}
                    </option>
                </select>
            </div>

            <fieldset>
                <legend class="form-label fs-6">Lecturers</legend>
                <div v-for="lecturer in lecturers" :key="lecturer.lecturerID" class="form-check">
                    <input
                        :id="'lecturer-' + lecturer.lecturerID"
                        v-model="form.lecturerIDs"
                        :value="lecturer.lecturerID"
                        type="checkbox"
                        class="form-check-input"
                    />
                    <label :for="'lecturer-' + lecturer.lecturerID" class="form-check-label">
                        {{ lecturer.name }}
                    </label>
                </div>
                <p v-if="lecturers.length === 0" class="mb-0">No lecturers to pick from yet</p>
            </fieldset>
        </form>

        <!-- buttons sit on the right with Cancel first, like the student and cohort pop-ups -->
        <!-- form="subjectForm" links the footer button back up to the form -->
        <template #buttons>
            <div class="d-flex justify-content-end gap-2 w-100">
                <button type="button" class="btn btn-outline-primary" @click="$refs.dialog.close()">Cancel</button>
                <button type="submit" form="subjectForm" class="btn btn-primary" :disabled="isSaving">
                    Save subject
                </button>
            </div>
        </template>
    </PopupDialog>
</template>
