// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LoginForm from '../components/forms/loginForm.vue';
import { login } from '../services/authService';

vi.mock('../services/authService', () => ({
    login: vi.fn(),
}));

/**
 * Tests for the "Account locked" pop-up in loginForm.vue
 * jsdom has no showModal(), so a stand-in opens and closes the dialog
 * every test is laid out Arrange, Act, Assert
 */

// the form reads ?signedOut from the route and links to other pages, neither needs a real router
function mountForm() {
    return mount(LoginForm, {
        global: {
            mocks: { $route: { query: {} }, $router: { push: vi.fn() } },
            stubs: { RouterLink: { template: '<a><slot /></a>' } },
        },
    });
}

// fills in the boxes and presses Login
async function submitLogin(wrapper) {
    await wrapper.get('#username').setValue('ws000001');
    await wrapper.get('#password').setValue('Wrong1234!');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

describe('LoginForm locked pop-up', () => {
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
     * A 429 opens the pop-up with PHP's message in it (normal)
     */
    it('opens the locked pop-up with the message from PHP', async () => {
        // Arrange
        const lockedMessage = 'This account is locked after 3 failed login attempts. Try again in 15 minutes';
        login.mockRejectedValue({ response: { status: 429, data: { error: lockedMessage } } });
        const wrapper = mountForm();

        // Act
        await submitLogin(wrapper);

        // Assert
        expect(HTMLDialogElement.prototype.showModal).toHaveBeenCalledTimes(1);
        expect(wrapper.get('dialog').text()).toContain(lockedMessage);
    });

    /**
     * A 429 goes in the pop-up, not in the red message box (error)
     */
    it('does not show the locked message in the red box', async () => {
        // Arrange
        login.mockRejectedValue({ response: { status: 429, data: { error: 'Try again in 15 minutes' } } });
        const wrapper = mountForm();

        // Act
        await submitLogin(wrapper);

        // Assert
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    /**
     * A wrong password (401) shows the red box and leaves the pop-up shut (normal)
     */
    it('shows the red box for a wrong password and no pop-up', async () => {
        // Arrange
        login.mockRejectedValue({ response: { status: 401, data: { error: 'Username or password is incorrect' } } });
        const wrapper = mountForm();

        // Act
        await submitLogin(wrapper);

        // Assert
        expect(HTMLDialogElement.prototype.showModal).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toContain('Username or password is incorrect');
    });
});
