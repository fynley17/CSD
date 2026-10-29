// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Tests for idleTimer.js, the warning at 28 minutes, the logout check at 30, and typing or clicking
 * fake timers move the clock forward, so nothing waits for real minutes
 * every test is laid out Arrange, Act, Assert
 */

const MINUTE = 60 * 1000;

// idleTimer.js keeps its state inside itself, so every test loads its own fresh copy
let timer;
let showWarning;
let handleTimeout;
let pingServer;

describe('idleTimer', () => {
    beforeEach(async () => {
        vi.useFakeTimers();
        vi.resetModules();
        timer = await import('../services/idleTimer');
        showWarning = vi.fn();
        handleTimeout = vi.fn();
        pingServer = vi.fn();
        timer.setIdleHandlers(showWarning, handleTimeout, pingServer);
    });

    afterEach(() => {
        timer.stopIdleTimer();
        vi.useRealTimers();
    });

    /**
     * Warning at 28 minutes, logout check at 30, nothing a second before the warning (normal and boundary)
     */
    it('warns at 28 minutes and checks for a logout at 30', () => {
        // Arrange
        timer.startIdleTimer();

        // Act
        vi.advanceTimersByTime(28 * MINUTE - 1000);
        const warnedEarly = showWarning.mock.calls.length;
        vi.advanceTimersByTime(1000);
        const warnedAt28 = showWarning.mock.calls.length;
        vi.advanceTimersByTime(2 * MINUTE);

        // Assert
        expect(warnedEarly).toBe(0);
        expect(warnedAt28).toBe(1);
        expect(handleTimeout).toHaveBeenCalledTimes(1);
    });

    /**
     * A reply from PHP starts both countdowns again (normal)
     */
    it('starts again when PHP replies', () => {
        // Arrange, 20 minutes in
        timer.startIdleTimer();
        vi.advanceTimersByTime(20 * MINUTE);

        // Act, PHP replies, then another 20 minutes pass
        timer.restartIdleTimer();
        vi.advanceTimersByTime(20 * MINUTE);

        // Assert, 40 minutes in total but neither time is up
        expect(showWarning).not.toHaveBeenCalled();
        expect(handleTimeout).not.toHaveBeenCalled();
    });

    /**
     * Signing out cancels everything, even typing afterwards (error)
     */
    it('does nothing once stopped', () => {
        // Arrange, like after signing out
        timer.startIdleTimer();
        timer.stopIdleTimer();

        // Act
        vi.advanceTimersByTime(60 * MINUTE);
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'a' }));

        // Assert
        expect(showWarning).not.toHaveBeenCalled();
        expect(handleTimeout).not.toHaveBeenCalled();
        expect(pingServer).not.toHaveBeenCalled();
    });

    /**
     * Typing after 5 minutes tells PHP once, however many keys and clicks follow (normal)
     */
    it('tells PHP about typing once 5 minutes have passed', () => {
        // Arrange
        timer.startIdleTimer();
        vi.advanceTimersByTime(5 * MINUTE);

        // Act
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'a' }));
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'b' }));
        window.dispatchEvent(new MouseEvent('click'));

        // Assert
        expect(pingServer).toHaveBeenCalledTimes(1);
    });

    /**
     * A click a second before the 5 minutes are up doesn't ask PHP (boundary)
     */
    it('does not tell PHP again inside 5 minutes', () => {
        // Arrange
        timer.startIdleTimer();
        vi.advanceTimersByTime(5 * MINUTE - 1000);

        // Act
        window.dispatchEvent(new MouseEvent('click'));

        // Assert
        expect(pingServer).not.toHaveBeenCalled();
    });

    /**
     * While the warning is open, a key press doesn't count as answering it (error)
     */
    it('ignores typing while the warning is open', () => {
        // Arrange, the warning has opened
        timer.startIdleTimer();
        vi.advanceTimersByTime(28 * MINUTE);

        // Act
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));
        vi.advanceTimersByTime(2 * MINUTE);

        // Assert, PHP wasn't told, and the logout check still ran at 30 minutes
        expect(pingServer).not.toHaveBeenCalled();
        expect(handleTimeout).toHaveBeenCalledTimes(1);
    });
});
