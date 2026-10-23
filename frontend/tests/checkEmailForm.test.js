// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CheckEmailForm from '../components/forms/checkEmailForm.vue';
import { changeEmail, getPendingVerification, resendVerification } from '../services/verificationService';

vi.mock('../services/verificationService', () => ({
    getPendingVerification: vi.fn(),
    resendVerification: vi.fn(),
    changeEmail: vi.fn(),
}));

// the card reads ?notSent from the route and links to other pages, neither needs a real router
function mountForm(query = {}) {
    return mount(CheckEmailForm, {
        global: {
            mocks: { $route: { query } },
            stubs: { RouterLink: { template: '<a><slot /></a>' } },
        },
    });
}

describe('CheckEmailForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getPendingVerification.mockResolvedValue({ email: 'ws123456@weston.ac.uk', isEmailVerified: false });
    });

    it('shows the address the link was sent to', async () => {
        const wrapper = mountForm();
        await flushPromises();

        expect(wrapper.get('.verificationEmail').text()).toBe('ws123456@weston.ac.uk');
    });

    it('tells the student to resend when the first email failed', async () => {
        const wrapper = mountForm({ notSent: '1' });
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('We could not send the email');
    });

    it('asks the student to login when the session has ended', async () => {
        getPendingVerification.mockRejectedValue({
            response: { data: { error: 'Your session has ended. Login to verify your email address' } },
        });
        const wrapper = mountForm();
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Your session has ended');
        expect(wrapper.text()).toContain('Go to login');
    });

    it('shows a success message after resending', async () => {
        resendVerification.mockResolvedValue({ message: 'We have sent a new link to ws123456@weston.ac.uk' });
        const wrapper = mountForm();
        await flushPromises();

        await wrapper.get('button.loginButton').trigger('click');
        await flushPromises();

        expect(wrapper.get('.successMessage').text()).toContain('We have sent a new link');
    });

    it('shows PHP’s message when asked again too quickly', async () => {
        resendVerification.mockRejectedValue({
            response: { data: { error: 'Wait a minute before asking for another email' } },
        });
        const wrapper = mountForm();
        await flushPromises();

        await wrapper.get('button.loginButton').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Wait a minute');
    });

    it('stops an empty new address before it reaches PHP', async () => {
        const wrapper = mountForm();
        await flushPromises();

        await wrapper.get('button.registerButton').trigger('click');
        await wrapper.get('#newEmail').setValue('   ');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(changeEmail).not.toHaveBeenCalled();
        expect(wrapper.get('#newEmailError').text()).toBe('Error: Enter your email address');
    });

    it('updates the address shown once the new one is saved', async () => {
        changeEmail.mockResolvedValue({ message: 'We have sent a new link', email: 'ws654321@weston.ac.uk' });
        const wrapper = mountForm();
        await flushPromises();

        await wrapper.get('button.registerButton').trigger('click');
        await wrapper.get('#newEmail').setValue(' ws654321@weston.ac.uk ');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(changeEmail).toHaveBeenCalledWith('ws654321@weston.ac.uk');
        expect(wrapper.get('.verificationEmail').text()).toBe('ws654321@weston.ac.uk');
        expect(wrapper.find('form').exists()).toBe(false);
    });
});
