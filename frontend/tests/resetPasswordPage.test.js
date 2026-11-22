// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ResetPasswordPage from '../views/resetPasswordPage.vue';
import { resetPassword } from '../services/passwordResetService';

vi.mock('../services/passwordResetService', () => ({
    resetPassword: vi.fn(),
}));

const TOKEN = 'a'.repeat(64);

// Tests for the reset password page, each test is laid out Arrange, Act, Assert
function mountPage(query = { token: TOKEN }) {
    return mount(ResetPasswordPage, {
        global: {
            mocks: { $route: { query } },
            stubs: { RouterLink: { template: '<a><slot /></a>' } },
        },
    });
}

async function submit(wrapper, password, confirmPassword = password) {
    await wrapper.get('#newPassword').setValue(password);
    await wrapper.get('#confirmPassword').setValue(confirmPassword);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

describe('ResetPasswordPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    /**
     * The code from the link is sent with the new password, then they're pointed at login (normal)
     */
    it('resets the password with the code from the link', async () => {
        // Arrange
        resetPassword.mockResolvedValue({ message: 'Your password has been reset. You can now login' });
        const wrapper = mountPage();

        // Act
        await submit(wrapper, 'NewPass1!');

        // Assert
        expect(resetPassword).toHaveBeenCalledWith(TOKEN, 'NewPass1!', 'NewPass1!');
        expect(wrapper.get('[role="status"]').text()).toContain('Your password has been reset');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.text()).toContain('Go to login');
    });

    /**
     * Weak or mismatched passwords are caught before anything is sent (error)
     */
    it.each([
        ['weak', 'weak', 'at least 8 characters'],
        ['NewPass1!', 'NewPass2!', 'do not match'],
        ['', '', 'Enter a new password'],
    ])('turns down %j / %j before sending', async (password, confirmPassword, message) => {
        // Arrange
        const wrapper = mountPage();

        // Act
        await submit(wrapper, password, confirmPassword);

        // Assert
        expect(resetPassword).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toContain(message);
    });

    /**
     * An expired or used link shows PHP's message and offers a new link (error)
     */
    it("shows PHP's message when the link doesn't work", async () => {
        // Arrange
        resetPassword.mockRejectedValue({
            response: { data: { error: 'This reset link is not valid, has expired or has already been used' } },
        });
        const wrapper = mountPage();

        // Act
        await submit(wrapper, 'NewPass1!');

        // Assert
        const alert = wrapper.get('[role="alert"]').text();
        expect(alert).toContain('has expired');
        expect(alert).toContain('Ask for a new link');
    });

    /**
     * A link without its code can't reset anything, so there's no form (boundary)
     */
    it('shows no form when the link has no code', () => {
        // Act
        const wrapper = mountPage({});

        // Assert
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.get('[role="alert"]').text()).toContain('not complete');
    });
});
