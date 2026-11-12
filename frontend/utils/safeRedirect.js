/**
 * The router adds ?redirect= when a logged-out visitor opens a private page, so they go back there after logging in.
 * Only a path on this site is used, anything like //other-site.com or https://… is turned away,
 * so a link to the login page can't send someone to another website after they log in.
 * The router still checks the account type, so a student sent to a lecturer page ends up on their own dashboard.
 *
 * @param {unknown} redirect the ?redirect= value from the login page's URL
 * @returns {string|null} the path to go to, or null to use the dashboard instead
 */
export function safeRedirect(redirect) {
    if (typeof redirect !== 'string' || !redirect.startsWith('/') || redirect.startsWith('//')) {
        return null;
    }

    // browsers can read a backslash as a slash, so /\other-site.com is turned away as well
    if (redirect.includes('\\')) {
        return null;
    }

    return redirect;
}
