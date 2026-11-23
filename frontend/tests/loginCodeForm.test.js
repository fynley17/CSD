// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LoginCodeForm from '../components/forms/loginCodeForm.vue';
import { verifyLoginCode } from '../services/authService';

vi.mock('../services/authService', () => ({
    verifyLoginCode: vi.fn(),
    resendLoginCode: vi.fn(),
}));

/**
 * Tests for the security code card shown after the password when MFA is on
 * every test is laid out Arrange, Act, Assert
 */

// types a code in and presses Continue
async function submitCode(wrapper, code) {
    await wrapper.get('#securityCode').setValue(code);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

describe('LoginCodeForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    /**
     * The right code hands PHP's reply up to loginForm so it can go to the dashboard (normal)
     */
    it('passes the reply on when the code is right', async () => {
        // Arrange
        verifyLoginCode.mockResolvedValue({ accountType: 'student' });
        const wrapper = mount(LoginCodeForm, { props: { maskedEmail: 'w***@example.com' } });

        // Act
        await submitCode(wrapper, '123456');

        // Assert
        expect(verifyLoginCode).toHaveBeenCalledWith('123456');
        expect(wrapper.emitted('loggedIn')[0]).toEqual([{ accountType: 'student' }]);
    });

    /**
     * A wrong code shows PHP's message in the red box and doesn't log in (error)
     */
    it('shows the problem when the code is wrong', async () => {
        // Arrange
        verifyLoginCode.mockRejectedValue({ response: { status: 401, data: { error: 'Security code is incorrect' } } });
        const wrapper = mount(LoginCodeForm, { props: { maskedEmail: 'w***@example.com' } });

        // Act
        await submitCode(wrapper, '000000');

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toContain('There is a problem: Security code is incorrect');
        expect(wrapper.emitted('loggedIn')).toBeUndefined();
    });

    /**
     * When the email didn't send the card says so straight away and asks for a backup code (error)
     */
    it('asks for a backup code when the email did not send', () => {
        // Arrange and Act
        const wrapper = mount(LoginCodeForm, { props: { maskedEmail: 'w***@example.com', emailFailed: true } });

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toContain('Enter one of your backup codes');
        expect(wrapper.text()).not.toContain('We have sent');
    });

    /**
     * The 3rd wrong code locks the account, so loginForm is told to show the locked pop-up (boundary)
     */
    it('tells loginForm when the account gets locked', async () => {
        // Arrange
        const lockedMessage = 'This account is locked after 3 failed login attempts. Try again in 15 minutes';
        verifyLoginCode.mockRejectedValue({ response: { status: 429, data: { error: lockedMessage } } });
        const wrapper = mount(LoginCodeForm, { props: { maskedEmail: 'w***@example.com' } });

        // Act
        await submitCode(wrapper, '000000');

        // Assert
        expect(wrapper.emitted('locked')[0]).toEqual([lockedMessage]);
    });
});
