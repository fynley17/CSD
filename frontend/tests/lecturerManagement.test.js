// @vitest-environment jsdom

// lecturer management page tests, the real table and pop ups with a fake service underneath
// showModal() isnt in jsdom so beforeEach adds a pretend one like the form tests
// Arrange, Act, Assert
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LecturerManagement from '../views/lecturerManagement.vue';
import { deleteLecturer, getLecturers, setLecturerEnabled } from '../services/administratorService';

vi.mock('../services/administratorService', () => ({
    getLecturers: vi.fn(),
    getRecentLecturers: vi.fn(),
    updateLecturer: vi.fn(),
    setLecturerEnabled: vi.fn(),
    deleteLecturer: vi.fn(),
}));

// one enabled lecturer is enough for these
const lecturer = {
    userID: 'lecturer-1',
    firstName: 'Test',
    lastName: 'Lecturer',
    name: 'Test Lecturer',
    staffID: 'L1001',
    email: 'test.lecturer@example.test',
    isEnabled: true,
    lastLogin: null,
};

/**
 * Opens the page and waits for the table to load
 */
async function openPage() {
    const page = mount(LecturerManagement);
    await flushPromises();
    return page;
}

/**
 * Clicks a row button then the go ahead button in the confirm box
 * the confirm box is the first dialog on the page, Cancel is its first button and go ahead the second
 */
async function pressAndConfirm(page, rowLabel) {
    await page.get(`[aria-label="${rowLabel}"]`).trigger('click');
    await page.findAll('dialog')[0].findAll('.popupFooter button')[1].trigger('click');
    await flushPromises();
}

describe('LecturerManagement', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        HTMLDialogElement.prototype.showModal = vi.fn(function showModal() {
            this.setAttribute('open', '');
        });
        HTMLDialogElement.prototype.close = vi.fn(function close() {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        });
        vi.mocked(getLecturers).mockResolvedValue([lecturer]);
    });

    /**
     * Disable asks first, then turns the account off and the green box has Undo (normal)
     */
    it('disables after the confirm box and offers undo', async () => {
        // Arrange
        vi.mocked(setLecturerEnabled).mockResolvedValue({ message: 'Status updated' });
        const page = await openPage();

        // Act
        await pressAndConfirm(page, 'Disable account for Test Lecturer');

        // Assert
        expect(setLecturerEnabled).toHaveBeenCalledWith('lecturer-1', false);
        expect(page.get('[role="status"]').text()).toContain('Success: Status updated');
        expect(page.find('[aria-label="Undo for Test Lecturer"]').exists()).toBe(true);
    });

    /**
     * Delete refused by php, the red box shows why and there's no green box (error)
     */
    it('shows the message from PHP when a delete is refused', async () => {
        // Arrange
        vi.mocked(deleteLecturer).mockRejectedValue({
            response: { status: 400, data: { error: "Administrator accounts can't be changed on this page" } },
        });
        const page = await openPage();

        // Act
        await pressAndConfirm(page, 'Delete Test Lecturer');

        // Assert
        expect(deleteLecturer).toHaveBeenCalledWith('lecturer-1');
        expect(page.get('[role="alert"]').text()).toBe(
            "There is a problem: Administrator accounts can't be changed on this page",
        );
        expect(page.find('.alert-success').exists()).toBe(false);
    });
});
