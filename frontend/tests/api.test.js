import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Tests for how api.js looks after the secret code (CSRF token)
 * the backend is faked, so nothing is sent anywhere
 * each test is laid out Arrange, Act, Assert
 */

// every request the pretend backend gets, in order, i.e. 'GET /csrf-token'
let seen = [];
// what the pretend backend answers each POST with, one answer per call
let postAnswers = [];
let tokensMade = 0;

/**
 * Pretend backend, gives out a new code on GET /csrf-token and answers each POST from postAnswers
 */
function fakeBackend(config) {
    const method = config.method.toUpperCase();
    seen.push(`${method} ${config.url}`);

    if (method === 'GET') {
        tokensMade++;
        return Promise.resolve({ data: { csrfToken: `code-${tokensMade}` }, status: 200, config });
    }

    const status = postAnswers.shift() || 200;
    const reply = { data: {}, status, config };
    if (status >= 400) {
        return Promise.reject({ response: reply, config });
    }
    return Promise.resolve(reply);
}

// api.js remembers the code inside itself, so every test loads its own fresh copy
let api;

describe('api secret code', () => {
    beforeEach(async () => {
        vi.resetModules();
        api = (await import('../services/api')).default;
        seen = [];
        postAnswers = [];
        tokensMade = 0;
        api.defaults.adapter = fakeBackend;
    });

    /**
     * The second POST reuses the saved code instead of asking again (normal)
     */
    it('asks for the secret code once and reuses it', async () => {
        // Arrange, nothing to set up, a new api.js has no code saved yet

        // Act
        await api.post('/first');
        await api.post('/second');

        // Assert, one GET for the code, then both POSTs
        expect(seen).toEqual(['GET /csrf-token', 'POST /first', 'POST /second']);
    });

    /**
     * An out of date code (403) fetches a new one and sends the request again (error)
     */
    it('fetches a new code and tries once more after a 403', async () => {
        // Arrange, the first answer is a 403, the second goes through
        postAnswers = [403, 200];

        // Act
        const response = await api.post('/change');

        // Assert
        expect(response.status).toBe(200);
        expect(seen.filter((call) => call === 'POST /change')).toHaveLength(2);
        expect(seen).toContain('GET /csrf-token');
    });

    /**
     * A second 403 is a real refusal, so it's passed on and not tried a 3rd time (boundary)
     */
    it('gives up after one retry and passes the 403 on', async () => {
        // Arrange
        postAnswers = [403, 403];

        // Act
        const attempt = api.post('/change');

        // Assert
        await expect(attempt).rejects.toHaveProperty('response.status', 403);
        expect(seen.filter((call) => call === 'POST /change')).toHaveLength(2);
    });

    /**
     * Other errors, like a wrong password (401), aren't tried again (error)
     */
    it('does not retry other errors', async () => {
        // Arrange
        postAnswers = [401];

        // Act
        const attempt = api.post('/login');

        // Assert
        await expect(attempt).rejects.toHaveProperty('response.status', 401);
        expect(seen.filter((call) => call === 'POST /login')).toHaveLength(1);
    });

    /**
     * After login PHP makes a new code, so the saved one is thrown away and the next POST asks again (normal)
     */
    it('asks for a new code after login', async () => {
        // Arrange, a code is saved by a first POST
        await api.post('/something');
        await api.post('/login');
        seen = [];

        // Act
        await api.post('/after-login');

        // Assert
        expect(seen).toEqual(['GET /csrf-token', 'POST /after-login']);
    });
});
