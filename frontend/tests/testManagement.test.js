// @vitest-environment jsdom

// Tests for who can see the Create test button on the test management page
// laid out Arrange (who is logged in), Act (open the page), Assert (is the button there)
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { getCurrentUser } from '../services/authService';
import TestManagement from '../views/testManagement.vue';

vi.mock('../services/authService', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../services/testService', () => ({ deleteTest: vi.fn() }));

/**
 * Opens the page as the given account type, the table and form are stubbed as they load from PHP
 */
async function openPageAs(accountType) {
    getCurrentUser.mockResolvedValue({ accountType });
    const page = mount(TestManagement, {
        global: { stubs: { TestManagementTable: true, TestForm: true, PopupDialog: true } },
    });
    await flushPromises();

    return page;
}

function createButton(page) {
    return page.findAll('button').find((button) => button.text() === 'Create test');
}

describe('TestManagement', () => {
    /**
     * Lecturers set the tests, so they get the button (normal)
     */
    it('shows Create test to lecturers', async () => {
        // Act
        const page = await openPageAs('lecturer');

        // Assert
        expect(createButton(page)).toBeDefined();
    });

    /**
     * Admins are lecturers too, so they can set tests as well (normal)
     */
    it('shows Create test to admins', async () => {
        // Act
        const page = await openPageAs('admin');

        // Assert
        expect(createButton(page)).toBeDefined();
    });

    /**
     * Anyone else doesn't see it, even if they reach the page (edge)
     */
    it('hides Create test from students', async () => {
        // Act
        const page = await openPageAs('student');

        // Assert
        expect(createButton(page)).toBeUndefined();
    });
});
