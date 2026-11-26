// @vitest-environment jsdom

// Tests for the header, checks an admin gets their own page links, the Administrator label
// and a logo that goes to the admin dashboard, and that lecturers and visitors keep the header they had before
// each test is laid out Arrange (pick the account), Act (load the header), Assert (check what shows)
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import Navbar from '../components/common/navbar.vue';
import { getCurrentUser, logout } from '../services/authService';

// swaps the real getCurrentUser() for a pretend one, so no PHP or database is needed
// each test then picks which account the pretend one hands back
vi.mock('../services/authService', () => ({
    getCurrentUser: vi.fn(),
    logout: vi.fn(),
}));

/**
 * Puts the header on a blank page on its own, the same header app.vue shows on every real page
 * @returns the header, ready to check
 */
async function loadHeader() {
    // the header needs a router to work, this one sends every address to an empty page
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div></div>' } }],
    });
    router.push('/');
    await router.isReady();

    // mount() builds the header in memory, no browser window opens
    const header = mount(Navbar, { global: { plugins: [router] } });
    // the header asks getCurrentUser() in the background, this waits for the answer before anything is checked
    await flushPromises();
    return header;
}

/**
 * Gets the names of the page links in the desktop header, in the order they show
 * @param header the header from loadHeader()
 * @returns the link names, an empty list if there aren't any
 */
function desktopLinkNames(header) {
    return header.findAll('.desktopStudentNavigation a, .desktopLecturerNavigation a').map((link) => link.text());
}

describe('Navbar', () => {
    // clears the pretend answer before each test, so one test can't change the next one
    beforeEach(() => {
        vi.mocked(getCurrentUser).mockReset();
        HTMLDialogElement.prototype.showModal = vi.fn(function showModal() {
            this.setAttribute('open', '');
        });
        HTMLDialogElement.prototype.close = vi.fn(function close() {
            this.removeAttribute('open');
        });
    });

    /**
     * An admin gets the admin pages and the lecturer pages, with Administrator next to the account icon (normal)
     */
    it('shows the administrator links and label to an admin', async () => {
        // Arrange
        // mockResolvedValue sets what the pretend getCurrentUser() hands back - here an admin account
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'adm01', accountType: 'admin' });

        // Act
        const header = await loadHeader();

        // Assert
        expect(desktopLinkNames(header)).toEqual([
            'Dashboard',
            'Lecturers',
            'Subjects',
            'Cohorts',
            'Students',
            'Tests',
            'Questions',
        ]);
        expect(header.get('.desktopActions .accountBadge').text()).toBe('Administrator');
        expect(header.get('.navbar-brand').attributes('href')).toBe('/admin-dashboard');
    });

    /**
     * A lecturer's header stays the same as before, their own links and no Administrator label (normal)
     */
    it('shows the lecturer links without the administrator label', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'lec01', accountType: 'lecturer' });

        // Act
        const header = await loadHeader();

        // Assert
        expect(desktopLinkNames(header)).toEqual(['Dashboard', 'Students', 'Tests', 'Questions']);
        expect(header.find('.accountBadge').exists()).toBe(false);
        expect(header.get('.navbar-brand').attributes('href')).toBe('/lecturer-dashboard');
    });

    /**
     * A student keeps their own three links and no Administrator label (normal)
     */
    it('shows the student links to a student', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'ws000001', accountType: 'student' });

        // Act
        const header = await loadHeader();

        // Assert
        expect(desktopLinkNames(header)).toEqual(['Tests', 'Leaderboard', 'Results']);
        expect(header.find('.accountBadge').exists()).toBe(false);
    });

    /** A student sees confirmation before signing out (normal) */
    it('opens confirmation instead of signing out immediately', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'ws000001', accountType: 'student' });
        vi.mocked(logout).mockReset();

        const header = await loadHeader();
        const signOutButton = header
            .findAll('.desktopActions button')
            .find((button) => button.text().includes('Sign out'));

        // Act
        await signOutButton.trigger('click');
        await flushPromises();

        // Assert
        expect(header.find('dialog[aria-label="Confirm Sign Out"]').exists()).toBe(true);
        expect(header.find('dialog[aria-label="Confirm Sign Out"]').text()).toContain('Sign out of RevisionHub?');
        expect(logout).not.toHaveBeenCalled();
    });

    it('closes the confirmation without signing out when cancelled', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'ws000001', accountType: 'student' });
        vi.mocked(logout).mockReset();

        const header = await loadHeader();
        const signOutButton = header
            .findAll('.desktopActions button')
            .find((button) => button.text().includes('Sign out'));
        await signOutButton.trigger('click');
        await flushPromises();

        // Act
        await header.get('dialog[aria-label="Confirm Sign Out"] .signOutCancelButton').trigger('click');
        await flushPromises();

        // Assert
        expect(header.find('dialog[aria-label="Confirm Sign Out"]').exists()).toBe(false);
        expect(logout).not.toHaveBeenCalled();
    });

    it('shows no page links to a visitor', async () => {
        // Arrange
        // null is what getCurrentUser() gives back when nobody's logged in
        vi.mocked(getCurrentUser).mockResolvedValue(null);

        // Act
        const header = await loadHeader();

        // Assert
        expect(desktopLinkNames(header)).toEqual([]);
        expect(header.find('.accountBadge').exists()).toBe(false);
        expect(header.get('.navbar-brand').attributes('href')).toBe('/');
        expect(header.find('.desktopActions button').exists()).toBe(false);
    });

    /**
     * An account type the header doesn't know, i.e. a typo in the database
     * gets no links and isn't treated as an admin (error)
     */
    it('shows no page links for an unknown account type', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'odd01', accountType: 'superuser' });

        // Act
        const header = await loadHeader();

        // Assert
        expect(desktopLinkNames(header)).toEqual([]);
        expect(header.find('.accountBadge').exists()).toBe(false);
        expect(header.get('.navbar-brand').attributes('href')).toBe('/');
    });

    /**
     * Every signed in account type gets the My profile icon next to Sign out, on desktop and in the phone menu (normal)
     */
    it.each(['student', 'lecturer'])('shows a %s the My profile icon next to Sign out', async (accountType) => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'user01', accountType });

        // Act
        const header = await loadHeader();

        // Assert
        const desktopLink = header.get('.desktopActions .profileLink');
        expect(desktopLink.attributes('href')).toBe('/profile');
        // the icon has no words, so the label is what a screen reader says
        expect(desktopLink.attributes('aria-label')).toBe('My profile');
        expect(desktopLink.get('i').classes()).toContain('bi-person-circle');
        expect(desktopLink.get('i').attributes('aria-hidden')).toBe('true');
        // sits straight before the Sign out button
        expect(desktopLink.element.nextElementSibling.textContent.trim()).toBe('Sign out');

        const mobileLink = header.get('#mobileNavigation .profileLink');
        expect(mobileLink.attributes('href')).toBe('/profile');
        expect(mobileLink.text()).toBe('My profile');
    });

    /**
     * An admin's Administrator label becomes the My profile link, and the label still says Administrator (normal)
     */
    it('turns the administrator label into the My profile link', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'adm01', accountType: 'admin' });

        // Act
        const header = await loadHeader();

        // Assert
        const desktopLink = header.get('.desktopActions .accountBadge');
        expect(desktopLink.element.tagName).toBe('A');
        expect(desktopLink.attributes('href')).toBe('/profile');
        // the visible word is part of the label, so voice control users can say what they see
        expect(desktopLink.attributes('aria-label')).toBe('My profile (Administrator)');
        expect(header.findAll('.desktopActions .profileLink')).toHaveLength(1);
        expect(header.get('#mobileNavigation .profileLink').text()).toBe('Administrator');
    });

    /**
     * Nobody logged in, so there's no profile to link to (boundary)
     */
    it('shows no My profile icon to a visitor', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue(null);

        // Act
        const header = await loadHeader();

        // Assert
        expect(header.find('.profileLink').exists()).toBe(false);
    });
});
