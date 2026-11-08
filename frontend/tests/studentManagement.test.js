// @vitest-environment jsdom

// Tests for who can see the Add student button on the student management page
// laid out Arrange (who is logged in), Act (open the page), Assert (is the button there)
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { getCurrentUser } from '../services/authService';
import StudentManagement from '../views/studentManagement.vue';

vi.mock('../services/authService', () => ({ getCurrentUser: vi.fn() }));

/**
 * Opens the page as the given account type, the table and form are stubbed as they load from PHP
 */
async function openPageAs(accountType) {
    getCurrentUser.mockResolvedValue({ accountType });
    const page = mount(StudentManagement, {
        global: { stubs: { StudentManagementTable: true, StudentForm: true } },
    });
    await flushPromises();

    return page;
}

function addButton(page) {
    return page.findAll('button').find((button) => button.text() === '+ Add student');
}

describe('StudentManagement', () => {
    /**
     * Lecturers look after their students, so they get the button (normal)
     */
    it('shows Add student to lecturers', async () => {
        // Act
        const page = await openPageAs('lecturer');

        // Assert
        expect(addButton(page)).toBeDefined();
    });

    /**
     * Admins can add students too (normal)
     */
    it('shows Add student to admins', async () => {
        // Act
        const page = await openPageAs('admin');

        // Assert
        expect(addButton(page)).toBeDefined();
    });

    /**
     * Anyone else doesn't see it, even if they reach the page (edge)
     */
    it('hides Add student from students', async () => {
        // Act
        const page = await openPageAs('student');

        // Assert
        expect(addButton(page)).toBeUndefined();
    });
});
