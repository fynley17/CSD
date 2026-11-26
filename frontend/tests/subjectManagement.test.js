// @vitest-environment jsdom

// tests for the subject management page, table and confirm box are real but the service is faked
// showModal() isnt in jsdom so beforeEach adds a pretend one like the lecturer page tests
// Arrange, Act, Assert
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectManagement from '../views/subjectManagement.vue';
import { getSubjects, setSubjectActive } from '../services/subjectService';

vi.mock('../services/subjectService', () => ({
    getSubjects: vi.fn(),
    getLecturerOptions: vi.fn(),
    getCohorts: vi.fn(),
    createSubject: vi.fn(),
    updateSubject: vi.fn(),
    setSubjectActive: vi.fn(),
}));

// one active and one archived subject covers both buttons
const activeSubject = {
    subjectID: 'subject-1',
    subjectName: 'Databases',
    cohortName: 'DTS 2026',
    testCount: 0,
    lecturerNames: 'Test Lecturer',
    isActive: true,
};
const archivedSubject = { ...activeSubject, subjectID: 'subject-2', subjectName: 'Legacy Systems', isActive: false };

/**
 * Opens the page and waits for the table to load
 */
async function openPage() {
    const page = mount(SubjectManagement);
    await flushPromises();
    return page;
}

/**
 * Clicks a row button then the go ahead button in the confirm box (its the second button)
 */
async function pressAndConfirm(page, rowLabel) {
    await page.get(`[aria-label="${rowLabel}"]`).trigger('click');
    await page.findAll('dialog')[0].findAll('.popupFooter button')[1].trigger('click');
    await flushPromises();
}

describe('SubjectManagement', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        HTMLDialogElement.prototype.showModal = vi.fn(function showModal() {
            this.setAttribute('open', '');
        });
        HTMLDialogElement.prototype.close = vi.fn(function close() {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        });
        vi.mocked(getSubjects).mockResolvedValue([activeSubject, archivedSubject]);
    });

    /**
     * Archive asks first with the subject name, then archives it and says so (normal)
     */
    it('archives a subject after the confirm box', async () => {
        // Arrange
        vi.mocked(setSubjectActive).mockResolvedValue({});
        const page = await openPage();

        // Act
        await page.get('[aria-label="Archive Databases"]').trigger('click');
        const confirmText = page.findAll('dialog')[0].text();
        await page.findAll('dialog')[0].findAll('.popupFooter button')[1].trigger('click');
        await flushPromises();

        // Assert
        expect(confirmText).toContain('Archive "Databases"?');
        expect(setSubjectActive).toHaveBeenCalledWith('subject-1', false);
        expect(page.get('.toastMessage.successMessage').text()).toContain('Subject archived successfully.');
    });

    /**
     * An archived subject can be restored, also asking first (normal)
     */
    it('restores an archived subject after the confirm box', async () => {
        // Arrange
        vi.mocked(setSubjectActive).mockResolvedValue({});
        const page = await openPage();

        // Act
        await pressAndConfirm(page, 'Restore Legacy Systems');

        // Assert
        expect(setSubjectActive).toHaveBeenCalledWith('subject-2', true);
        expect(page.get('.toastMessage.successMessage').text()).toContain('Subject restored successfully.');
    });

    /**
     * PHP refuses the archive, the red toast says why and theres no success message (error)
     */
    it('shows the message from PHP when an archive is refused', async () => {
        // Arrange
        vi.mocked(setSubjectActive).mockRejectedValue({
            response: { status: 400, data: { error: 'Unable to archive the subject.' } },
        });
        const page = await openPage();

        // Act
        await pressAndConfirm(page, 'Archive Databases');

        // Assert
        expect(page.get('.toastMessage.errorMessage').text()).toContain('Unable to archive the subject.');
        expect(page.find('.toastMessage.successMessage').exists()).toBe(false);
    });
});
