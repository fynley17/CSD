// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RegistrationForm from '../components/forms/registrationForm.vue';
import api from '../services/api';

vi.mock('../services/api', () => ({
    default: { get: vi.fn(), post: vi.fn() },
}));

// Tests for the show password buttons on the registration form, each test is laid out Arrange, Act, Assert
async function mountForm() {
    const wrapper = mount(RegistrationForm, {
        global: {
            mocks: { $router: { push: vi.fn() } },
            stubs: { RouterLink: { template: '<a><slot /></a>' } },
        },
    });
    await flushPromises();
    return wrapper;
}

describe('RegistrationForm show password buttons', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockResolvedValue({ data: [] });
    });

    /**
     * Both boxes start hidden, with an eye button that says what it does (normal)
     */
    it('hides both passwords to start with', async () => {
        // Act
        const wrapper = await mountForm();

        // Assert
        expect(wrapper.get('#password').attributes('type')).toBe('password');
        expect(wrapper.get('#confirmPassword').attributes('type')).toBe('password');
        const buttons = wrapper.findAll('.passwordToggle');
        expect(buttons.map((button) => button.attributes('aria-label'))).toEqual([
            'Show password',
            'Show confirm password',
        ]);
        expect(buttons[0].find('i').classes()).toContain('bi-eye');
    });

    /**
     * The eye shows the password, swaps to the crossed out eye, and hides it again on a second press (normal)
     */
    it('shows and hides the password', async () => {
        // Arrange
        const wrapper = await mountForm();
        const button = wrapper.findAll('.passwordToggle')[0];

        // Act
        await button.trigger('click');
        const typeWhenShown = wrapper.get('#password').attributes('type');
        const iconWhenShown = button.find('i').classes();
        const labelWhenShown = button.attributes('aria-label');
        await button.trigger('click');

        // Assert
        expect(typeWhenShown).toBe('text');
        expect(iconWhenShown).toContain('bi-eye-slash');
        expect(labelWhenShown).toBe('Hide password');
        expect(button.attributes('aria-pressed')).toBe('false');
        expect(wrapper.get('#password').attributes('type')).toBe('password');
    });

    /**
     * Each button only shows its own box (boundary)
     */
    it('shows the confirm password without showing the password', async () => {
        // Arrange
        const wrapper = await mountForm();

        // Act
        await wrapper.findAll('.passwordToggle')[1].trigger('click');

        // Assert
        expect(wrapper.get('#confirmPassword').attributes('type')).toBe('text');
        expect(wrapper.get('#password').attributes('type')).toBe('password');
    });

    /**
     * Pressing an eye button never sends the form (error)
     */
    it('does not submit the form', async () => {
        // Arrange
        const wrapper = await mountForm();

        // Act
        await wrapper.findAll('.passwordToggle')[0].trigger('click');

        // Assert
        expect(api.post).not.toHaveBeenCalled();
    });
});
