// @vitest-environment jsdom

// Tests for the My profile page, the profile service is pretend so no PHP or database is needed
// each test is laid out Arrange, Act, Assert
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProfilePage from '../views/profilePage.vue';
import { changePassword, getProfile, updateProfile } from '../services/profileService';

vi.mock('../services/profileService', () => ({
    getProfile: vi.fn(),
    updateProfile: vi.fn(),
    changePassword: vi.fn(),
}));

const STUDENT = {
    username: 'ws000001',
    accountType: 'student',
    firstName: 'Sam',
    lastName: 'Smith',
    email: 'sam@example.com',
    pendingEmail: null,
    cohortName: 'Computing 2026',
};

/**
 * Opens the page and waits for the pretend profile to load
 */
async function loadPage(profile = STUDENT) {
    vi.mocked(getProfile).mockResolvedValue(profile);
    const wrapper = mount(ProfilePage);
    await flushPromises();
    return wrapper;
}

/**
 * Reads the read only account details as label: value pairs
 */
function accountDetails(wrapper) {
    return Object.fromEntries(
        wrapper.findAll('.accountDetails > div').map((row) => [row.get('dt').text(), row.get('dd').text()]),
    );
}

async function fillPasswords(wrapper, current, newPassword, confirm = newPassword) {
    await wrapper.get('#currentPassword').setValue(current);
    await wrapper.get('#newPassword').setValue(newPassword);
    await wrapper.get('#confirmNewPassword').setValue(confirm);
}

describe('ProfilePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    /**
     * A student sees their student number, account type and cohort, with their names and email ready to change (normal)
     */
    it('shows a student their account and fills in the form', async () => {
        // Act
        const wrapper = await loadPage();

        // Assert
        expect(accountDetails(wrapper)).toEqual({
            'Student number': 'ws000001',
            'Account type': 'Student',
            Cohort: 'Computing 2026',
        });
        expect(wrapper.get('#profileFirstName').element.value).toBe('Sam');
        expect(wrapper.get('#profileLastName').element.value).toBe('Smith');
        expect(wrapper.get('#profileEmail').element.value).toBe('sam@example.com');
        // the username, type and cohort can only be read, there are no boxes for them
        expect(wrapper.findAll('input[type="text"], input[type="email"]')).toHaveLength(3);
    });

    /**
     * Staff see a staff ID and no cohort (normal)
     */
    it('shows a lecturer their staff ID without a cohort', async () => {
        // Act
        const wrapper = await loadPage({
            ...STUDENT,
            username: 'L1001',
            accountType: 'lecturer',
            cohortName: undefined,
        });

        // Assert
        expect(accountDetails(wrapper)).toEqual({ 'Staff ID': 'L1001', 'Account type': 'Lecturer' });
    });

    /**
     * The page says if it couldn't load, instead of showing an empty form (error)
     */
    it('shows a message when the profile cannot be loaded', async () => {
        // Arrange
        vi.mocked(getProfile).mockRejectedValue({ response: { data: { error: 'Your session has ended' } } });

        // Act
        const wrapper = mount(ProfilePage);
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toContain('Your session has ended');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    /**
     * Changed names and email are sent, and PHP's message and saved details are shown (normal)
     */
    it('saves the names and email', async () => {
        // Arrange
        const wrapper = await loadPage();
        vi.mocked(updateProfile).mockResolvedValue({
            message: 'Your details have been saved. We have sent a link to new@example.com',
            profile: { ...STUDENT, firstName: 'Samuel', pendingEmail: 'new@example.com' },
        });

        // Act
        await wrapper.get('#profileFirstName').setValue('Samuel');
        await wrapper.get('#profileEmail').setValue('new@example.com');
        await wrapper.findAll('form')[0].trigger('submit');
        await flushPromises();

        // Assert
        expect(updateProfile).toHaveBeenCalledWith('Samuel', 'Smith', 'new@example.com');
        expect(wrapper.get('[role="status"]').text()).toContain('We have sent a link to new@example.com');
        // the old address stays in the box, the new one is shown as waiting
        expect(wrapper.get('#profileEmail').element.value).toBe('sam@example.com');
        expect(wrapper.get('#profilePendingEmail').text()).toContain('new@example.com');
    });

    /**
     * Bad names and a badly formatted email are caught before anything is sent (error)
     */
    it('checks the details before sending them', async () => {
        // Arrange
        const wrapper = await loadPage();

        // Act
        await wrapper.get('#profileFirstName').setValue('S');
        await wrapper.get('#profileLastName').setValue('Sm1th');
        await wrapper.get('#profileEmail').setValue('not-an-email');
        await wrapper.findAll('form')[0].trigger('submit');
        await flushPromises();

        // Assert
        expect(updateProfile).not.toHaveBeenCalled();
        const links = wrapper.findAll('.messageList a').map((link) => link.attributes('href'));
        expect(links).toEqual(['#profileFirstName', '#profileLastName', '#profileEmail']);
        expect(wrapper.get('#profileEmail').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#profileEmail').attributes('aria-describedby')).toContain('profileEmailError');
    });

    /**
     * PHP's reason is shown when it refuses the details, i.e. the email is already in use (error)
     */
    it('shows the server message when the details are refused', async () => {
        // Arrange
        const wrapper = await loadPage();
        vi.mocked(updateProfile).mockRejectedValue({
            response: { data: { error: 'Another account is already using this email address' } },
        });

        // Act
        await wrapper.get('#profileEmail').setValue('taken@example.com');
        await wrapper.findAll('form')[0].trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toContain('Another account is already using this email address');
    });

    /**
     * The password form sends all three boxes and empties them once it's changed (normal)
     */
    it('changes the password and clears the boxes', async () => {
        // Arrange
        const wrapper = await loadPage();
        vi.mocked(changePassword).mockResolvedValue({ message: 'Your password has been changed' });

        // Act
        await fillPasswords(wrapper, 'OldPass1!', 'NewPass1!');
        await wrapper.findAll('form')[1].trigger('submit');
        await flushPromises();

        // Assert
        expect(changePassword).toHaveBeenCalledWith('OldPass1!', 'NewPass1!', 'NewPass1!');
        expect(wrapper.get('[role="status"]').text()).toContain('Your password has been changed');
        expect(wrapper.get('#currentPassword').element.value).toBe('');
        expect(wrapper.get('#newPassword').element.value).toBe('');
    });

    /**
     * A weak or mismatched new password and a blank current one are caught before sending (error)
     */
    it('checks the passwords before sending them', async () => {
        // Arrange
        const wrapper = await loadPage();

        // Act
        await fillPasswords(wrapper, '', 'weak', 'different');
        await wrapper.findAll('form')[1].trigger('submit');
        await flushPromises();

        // Assert
        expect(changePassword).not.toHaveBeenCalled();
        const messages = wrapper.findAll('.messageList a').map((link) => link.text());
        expect(messages).toEqual([
            'Enter your current password',
            'Use at least 8 characters, with an uppercase letter, a lowercase letter, a number and a symbol',
            'Your new passwords do not match',
        ]);
    });

    /**
     * A wrong current password shows PHP's message and keeps what they typed (error)
     */
    it('shows the server message for a wrong current password', async () => {
        // Arrange
        const wrapper = await loadPage();
        vi.mocked(changePassword).mockRejectedValue({
            response: { data: { error: 'Your current password is incorrect' } },
        });

        // Act
        await fillPasswords(wrapper, 'WrongPass1!', 'NewPass1!');
        await wrapper.findAll('form')[1].trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toContain('Your current password is incorrect');
        expect(wrapper.get('#newPassword').element.value).toBe('NewPass1!');
    });

    /**
     * Show passwords turns all three boxes into text so they can be checked (normal)
     */
    it('shows and hides the passwords', async () => {
        // Arrange
        const wrapper = await loadPage();

        // Act
        await wrapper.get('#showPasswords').setValue(true);

        // Assert
        for (const id of ['#currentPassword', '#newPassword', '#confirmNewPassword']) {
            expect(wrapper.get(id).attributes('type')).toBe('text');
        }
    });
});
