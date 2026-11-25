// @vitest-environment jsdom

// Tests for who can see the Add student button on the student management page
// laid out Arrange (who is logged in), Act (open the page), Assert (is the button there)
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { getCurrentUser } from '../services/authService';
import { updateStudentStatus } from '../services/studentService';
import StudentManagement from '../views/studentManagement.vue';

vi.mock('../services/authService', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../services/studentService', () => ({ updateStudentStatus: vi.fn() }));

const StudentManagementTableStub = {
    name: 'StudentManagementTable',
    emits: ['edit', 'success', 'error'],
    setup(_props, { expose }) {
        expose({ cohorts: [], pendingCount: 0, refreshStudents: vi.fn() });
        return {};
    },
    template: '<div />',
};

/**
 * Opens the page as the given account type, the table and form are stubbed as they load from PHP
 */
async function openPageAs(accountType) {
    getCurrentUser.mockResolvedValue({ accountType });
    const page = mount(StudentManagement, {
        global: { stubs: { StudentManagementTable: StudentManagementTableStub, StudentForm: true } },
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

    it('toggles the student applications filter on and off', async () => {
        const page = await openPageAs('admin');
        const applicationsButton = page
            .findAll('button')
            .find((button) => button.text().includes('Student Applications'));

        await applicationsButton.trigger('click');

        expect(page.get('#studentStatusFilter').element.value).toBe('Pending');
        expect(applicationsButton.attributes('aria-pressed')).toBe('true');

        await applicationsButton.trigger('click');

        expect(page.get('#studentStatusFilter').element.value).toBe('');
        expect(applicationsButton.attributes('aria-pressed')).toBe('false');
    });

    /**
     * Disabling a student offers Undo, pressing it turns them back on and Undo goes (normal)
     */
    it('turns the student back on when Undo is pressed', async () => {
        // Arrange
        vi.mocked(updateStudentStatus).mockResolvedValue({});
        const page = await openPageAs('lecturer');
        const student = { userID: 'student-1', name: 'Joe Bloggs', status: 'Active' };
        page.getComponent({ name: 'StudentManagementTable' }).vm.$emit(
            'success',
            'Student disabled successfully.',
            student,
        );
        await flushPromises();

        // Act
        await page.get('[aria-label="Undo for Joe Bloggs"]').trigger('click');
        await flushPromises();

        // Assert
        expect(updateStudentStatus).toHaveBeenCalledWith('student-1', true);
        expect(page.get('.toastMessage.successMessage').text()).toContain('Student enabled successfully.');
        expect(page.find('[aria-label="Undo for Joe Bloggs"]').exists()).toBe(false);
    });
});
