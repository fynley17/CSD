// @vitest-environment jsdom

// add/edit subject popup tests
// showModal() doesnt exist in jsdom so theres a fake one in beforeEach
// AAA layout
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectForm from '../components/forms/subjectForm.vue';
import { createSubject, getCohorts, getLecturerOptions, updateSubject } from '../services/subjectService';

vi.mock('../services/subjectService', () => ({
    createSubject: vi.fn(),
    getCohorts: vi.fn(),
    getLecturerOptions: vi.fn(),
    updateSubject: vi.fn(),
}));

/**
 * opens the form, give it a row for edit or leave it empty for add
 */
async function openForm(subject) {
    const wrapper = mount(SubjectForm);
    wrapper.vm.open(subject);
    await flushPromises();
    return wrapper;
}

describe('SubjectForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        HTMLDialogElement.prototype.showModal = vi.fn(function showModal() {
            this.setAttribute('open', '');
        });
        HTMLDialogElement.prototype.close = vi.fn(function close() {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        });
        vi.mocked(getCohorts).mockResolvedValue([{ cohortID: 'cohort-1', cohortName: 'Test Cohort' }]);
        vi.mocked(getLecturerOptions).mockResolvedValue([{ lecturerID: 'lecturer-1', name: 'Sample Lecturer' }]);
    });

    /**
     * fill in the blank form and save, should create it and tell the page (normal)
     */
    it('creates a subject from the blank form', async () => {
        // Arrange
        const wrapper = await openForm();
        await wrapper.get('#subjectName').setValue('Test Databases');
        await wrapper.get('#subjectCohort').setValue('cohort-1');
        await wrapper.get('#lecturer-lecturer-1').setValue(true);

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(createSubject).toHaveBeenCalledWith({
            subjectName: 'Test Databases',
            cohortID: 'cohort-1',
            lecturerIDs: ['lecturer-1'],
        });
        expect(wrapper.emitted('saved')).toHaveLength(1);
    });

    /**
     * Edit, boxes come filled in from the row and save goes to update instead (normal)
     */
    it('fills in the row and updates it', async () => {
        // Arrange
        const row = { subjectID: 'subject-1', subjectName: 'Test Databases', cohortID: 'cohort-1', lecturerIDs: [] };
        const wrapper = await openForm(row);

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.get('#subjectName').element.value).toBe('Test Databases');
        expect(updateSubject).toHaveBeenCalledWith('subject-1', {
            subjectName: 'Test Databases',
            cohortID: 'cohort-1',
            lecturerIDs: [],
        });
    });

    /**
     * php refuses (duplicate name) so the red box shows its message and nothing saves (error)
     */
    it('shows the message from PHP when the save fails', async () => {
        // Arrange
        const message = 'This cohort already has a subject with that name';
        vi.mocked(createSubject).mockRejectedValue({ response: { data: { error: message } } });
        const wrapper = await openForm();
        await wrapper.get('#subjectName').setValue('Test Databases');
        await wrapper.get('#subjectCohort').setValue('cohort-1');

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toBe('There is a problem: ' + message);
        expect(wrapper.emitted('saved')).toBeUndefined();
    });

    /**
     * the subjects cohort is disabled so its not in the list, it gets put back so the box isnt blank (boundary)
     */
    it('keeps the cohort of a subject whose cohort is disabled', async () => {
        // Arrange
        const row = {
            subjectID: 'subject-1',
            subjectName: 'Test Databases',
            cohortID: 'cohort-9',
            cohortName: 'Disabled Cohort',
            lecturerIDs: [],
        };

        // Act
        const wrapper = await openForm(row);

        // Assert
        expect(wrapper.get('#subjectCohort').element.value).toBe('cohort-9');
        expect(wrapper.text()).toContain('Disabled Cohort');
    });
});
