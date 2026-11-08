// @vitest-environment jsdom

// Add Lecturer and Edit Lecturer pop up tests
// jsdom has no showModal() so beforeEach puts a fake one in, same as the subject form tests
// Arrange, Act, Assert layout
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LecturerForm from '../components/forms/lecturerForm.vue';
import { createLecturer, updateLecturer } from '../services/administratorService';

vi.mock('../services/administratorService', () => ({
    createLecturer: vi.fn(),
    updateLecturer: vi.fn(),
}));

/**
 * Types the four boxes in the Add Lecturer pop up, made up test data
 */
async function fillIn(wrapper) {
    await wrapper.get('#lecturerFirstName').setValue('New');
    await wrapper.get('#lecturerLastName').setValue('Lecturer');
    await wrapper.get('#lecturerStaffID').setValue('L2001');
    await wrapper.get('#lecturerEmail').setValue('new.lecturer@example.test');
}

// a row like the lecturer table sends when Edit is pressed
const row = {
    userID: 'lecturer-1',
    firstName: 'Test',
    lastName: 'Lecturer',
    name: 'Test Lecturer',
    staffID: 'L1001',
    email: 'test.lecturer@example.test',
    isEnabled: true,
    lastLogin: null,
};

describe('LecturerForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        HTMLDialogElement.prototype.showModal = vi.fn(function showModal() {
            this.setAttribute('open', '');
        });
        HTMLDialogElement.prototype.close = vi.fn(function close() {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        });
    });

    /**
     * Boxes come filled in from the row, a changed name gets sent and the page is told (normal)
     */
    it('fills in the row and saves the changes', async () => {
        // Arrange
        vi.mocked(updateLecturer).mockResolvedValue({ message: 'Changes saved' });
        const wrapper = mount(LecturerForm);
        wrapper.vm.open(row);
        await wrapper.get('#lecturerFirstName').setValue('Edited');

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.get('#lecturerStaffID').element.value).toBe('L1001');
        expect(updateLecturer).toHaveBeenCalledWith('lecturer-1', {
            firstName: 'Edited',
            lastName: 'Lecturer',
            staffID: 'L1001',
            email: 'test.lecturer@example.test',
        });
        expect(wrapper.emitted('saved')[0]).toEqual(['Changes saved']);
    });

    /**
     * php says no (staff ID already used), the red box shows why and the page isnt told it saved (error)
     */
    it('shows the message from PHP when the save is refused', async () => {
        // Arrange
        vi.mocked(updateLecturer).mockRejectedValue({
            response: { status: 400, data: { error: 'An account with this staff ID already exists' } },
        });
        const wrapper = mount(LecturerForm);
        wrapper.vm.open(row);

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toBe(
            'There is a problem: An account with this staff ID already exists',
        );
        expect(wrapper.emitted('saved')).toBeUndefined();
    });

    /**
     * Add with Lecturer picked saves straight away and passes PHP's message to the page (normal)
     */
    it('adds a lecturer and passes the message on', async () => {
        // Arrange
        const message = 'Lecturer added. They have been emailed a link to set their password';
        vi.mocked(createLecturer).mockResolvedValue({ message, emailSent: true, userID: 'new-id' });
        const wrapper = mount(LecturerForm);
        wrapper.vm.open();
        await flushPromises();
        await fillIn(wrapper);

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('Add Lecturer');
        expect(createLecturer).toHaveBeenCalledWith({
            firstName: 'New',
            lastName: 'Lecturer',
            staffID: 'L2001',
            email: 'new.lecturer@example.test',
            role: 'lecturer',
        });
        expect(wrapper.emitted('saved')[0]).toEqual([message, 'L2001']);
    });

    /**
     * Picking Administrator, the first Save only shows the warning so it takes two presses to add one (boundary)
     */
    it('needs two Save presses to add an administrator', async () => {
        // Arrange
        vi.mocked(createLecturer).mockResolvedValue({ message: 'Administrator added', emailSent: true });
        const wrapper = mount(LecturerForm);
        wrapper.vm.open();
        await flushPromises();
        await fillIn(wrapper);
        await wrapper.get('#lecturerRole').setValue('admin');

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        // only one call after two presses means the first press just showed the warning
        expect(createLecturer).toHaveBeenCalledTimes(1);
        expect(createLecturer).toHaveBeenCalledWith(expect.objectContaining({ role: 'admin' }));
    });
});
