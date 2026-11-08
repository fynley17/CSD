/**
 * Turns the database time into UK date and time, i.e. 29/09/2026, 16:17
 * Shared by the lecturer and student tables so last login looks the same everywhere.
 * @param lastLogin the time from PHP, saved in UTC, or null if they've never logged in
 */
export function formatLastLogin(lastLogin) {
    if (!lastLogin) {
        return 'Never';
    }

    // Z on the end = UTC, the options below turn it into UK time
    const loginTime = new Date(lastLogin.replace(' ', 'T') + 'Z');

    return loginTime.toLocaleString('en-GB', {
        timeZone: 'Europe/London',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
