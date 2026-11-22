// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ForgotPasswordPage from '../views/forgotPasswordPage.vue';
import { requestPasswordReset } from '../services/passwordResetService';

vi.mock('../services/passwordResetService', () => ({
    requestPasswordReset: vi.fn(),
}));

// Tests for the forgot password page, each test is laid out Arrange, Act, Assert
function mountPage() {
    return mount(ForgotPasswordPage, {
        global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
    });
}

async function submit(wrapper, email) {
    await wrapper.get('#email').setValue(email);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

describe('ForgotPasswordPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    /**
     * An empty box is caught before anything is sent (error)
     */
    it('asks for an email address when the box is empty', async () => {
        // Arrange
        const wrapper = mountPage();

        // Act
        await submit(wrapper, '   ');

        // Assert
        expect(requestPasswordReset).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toContain('Enter your email address');
    });

    /**
     * The email is sent trimmed and PHP's reply is shown (normal)
     */
    it('sends the email and shows the reply', async () => {
        // Arrange
        requestPasswordReset.mockResolvedValue({
            message: 'If an account uses that email address, we have sent it a link',
        });
        const wrapper = mountPage();

        // Act
        await submit(wrapper, ' student@example.com ');

        // Assert
        expect(requestPasswordReset).toHaveBeenCalledWith('student@example.com');
        expect(wrapper.get('[role="status"]').text()).toContain('If an account uses that email address');
    });

    /**
     * PHP turning it down shows PHP's message (error)
     */
    it("shows PHP's message when the request is turned down", async () => {
        // Arrange
        requestPasswordReset.mockRejectedValue({
            response: { data: { error: 'Enter an email address in the correct format, like name@example.com' } },
        });
        const wrapper = mountPage();

        // Act
        await submit(wrapper, 'not-an-email');

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toContain('correct format');
    });
});
