// Idle timer, keeps track of how long it's been since the page last heard back from PHP
// PHP logs people out after 30 minutes without a request, so this warns them 2 minutes before
// and gives them the chance to stay logged in (WCAG compliance)

// same 30 minutes as IDLE_TIMEOUT_SECONDS in authentication.php, setTimeout counts in milliseconds
const IDLE_LIMIT_MS = 60 * 1000;
// the warning pop-up opens 2 minutes before the end
const WARNING_AT_MS = IDLE_LIMIT_MS - 40 * 1000;

// only runs while someone's logged in, a visitor on the landing page never gets the warning
let isRunning = false;
let warningTimer = null;
let timeoutTimer = null;

// what happens when each time is up, the idle warning pop-up hands these in when it loads
let onWarning = () => {};
let onTimeout = () => {};

/**
 * Lets the idle warning pop-up say what to do at 28 minutes and at 30 minutes
 * @param {Function} showWarning opens the "Are you still there?" pop-up
 * @param {Function} handleTimeout checks with PHP and sends them to the login page if they're logged out
 */
export function setIdleHandlers(showWarning, handleTimeout) {
    onWarning = showWarning;
    onTimeout = handleTimeout;
}

/**
 * Starts counting, called once PHP says someone's logged in
 */
export function startIdleTimer() {
    isRunning = true;
    restartIdleTimer();
}

/**
 * Starts the 30 minutes again, api.js calls this every time PHP replies
 * PHP starts its 30 minutes again on every request too, so the two stay in step
 */
export function restartIdleTimer() {
    if (!isRunning) {
        return;
    }

    clearTimeout(warningTimer);
    clearTimeout(timeoutTimer);
    warningTimer = setTimeout(() => onWarning(), WARNING_AT_MS);
    timeoutTimer = setTimeout(() => onTimeout(), IDLE_LIMIT_MS);
}

/**
 * Stops counting, for when they sign out or PHP says nobody's logged in
 */
export function stopIdleTimer() {
    isRunning = false;
    clearTimeout(warningTimer);
    clearTimeout(timeoutTimer);
}
