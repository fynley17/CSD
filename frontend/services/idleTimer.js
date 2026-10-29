// Idle timer, keeps track of how long it's been since PHP last heard from this page
// PHP logs people out after 30 minutes without a request, so this warns them 2 minutes before
// and gives them the chance to stay logged in (WCAG compliance)
// typing or clicking counts as being here too, so a student in the middle of a long test doesn't get logged out

// same 30 minutes as IDLE_TIMEOUT_SECONDS in authentication.php, setTimeout counts in milliseconds
const IDLE_LIMIT_MS = 30 * 60 * 1000;
// the warning pop-up opens 2 minutes before the end
const WARNING_AT_MS = IDLE_LIMIT_MS - 2 * 60 * 1000;
// while someone's typing or clicking, PHP is told at most this often, so it isn't asked on every key press
const PING_EVERY_MS = 5 * 60 * 1000;

// only runs while someone's logged in, a visitor on the landing page never gets the warning
let isRunning = false;
let warningTimer = null;
let timeoutTimer = null;
// true while the "Are you still there?" pop-up is open, typing in it mustn't count as an answer
let isWarningShowing = false;
// when PHP last heard from this page, any reply from PHP counts
let lastServerContact = 0;

// what happens when each time is up, the idle warning pop-up hands these in when it loads
let onWarning = () => {};
let onTimeout = () => {};
// what happens to tell PHP the person is still here
let onPing = () => {};

/**
 * Lets the idle warning pop-up say what to do at 28 minutes, at 30 minutes, and when someone is active
 * @param {Function} showWarning opens the "Are you still there?" pop-up
 * @param {Function} handleTimeout checks with PHP and sends them to the login page if they're logged out
 * @param {Function} pingServer asks PHP who's logged in, which also starts PHP's 30 minutes again
 */
export function setIdleHandlers(showWarning, handleTimeout, pingServer) {
    onWarning = showWarning;
    onTimeout = handleTimeout;
    onPing = pingServer;
}

/**
 * Runs on every key press and click while someone's logged in
 * the countdown only restarts once PHP has heard about it, or PHP could log them out before the warning shows
 * so this just tells PHP, and the reply restarts the countdown through restartIdleTimer()
 */
function onActivity() {
    // the warning has to be answered, a key press inside it doesn't count as staying logged in
    if (!isRunning || isWarningShowing) {
        return;
    }

    // PHP heard from this page recently, so there's nothing to tell it
    if (Date.now() - lastServerContact < PING_EVERY_MS) {
        return;
    }

    // set before asking, so a burst of key presses only sends one request
    lastServerContact = Date.now();
    onPing();
}

/**
 * Starts counting, called once PHP says someone's logged in
 */
export function startIdleTimer() {
    if (!isRunning) {
        window.addEventListener('keydown', onActivity);
        window.addEventListener('click', onActivity);
    }

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
    isWarningShowing = false;
    lastServerContact = Date.now();

    warningTimer = setTimeout(() => {
        isWarningShowing = true;
        onWarning();
    }, WARNING_AT_MS);
    timeoutTimer = setTimeout(() => onTimeout(), IDLE_LIMIT_MS);
}

/**
 * Stops counting, for when they sign out or PHP says nobody's logged in
 */
export function stopIdleTimer() {
    isRunning = false;
    isWarningShowing = false;
    clearTimeout(warningTimer);
    clearTimeout(timeoutTimer);
    window.removeEventListener('keydown', onActivity);
    window.removeEventListener('click', onActivity);
}
