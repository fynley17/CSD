import { describe, expect, it } from 'vitest';
import { safeRedirect } from '../utils/safeRedirect';

// Tests for safeRedirect(), which decides if the login page's ?redirect= can be followed
// each test is laid out Arrange, Act, Assert
describe('safeRedirect', () => {
    /**
     * A path on this site is kept, query and all (normal)
     */
    it.each(['/test-selection', '/testing/test-1', '/student-results?tab=recent'])('keeps %s', (path) => {
        // Arrange: path is the redirect

        // Act
        const result = safeRedirect(path);

        // Assert
        expect(result).toBe(path);
    });

    /**
     * Anything that could send someone to another website is turned away (error)
     */
    it.each(['https://evil.example', '//evil.example', '/\\evil.example', 'javascript:alert(1)', 'test-selection'])(
        'turns away %s',
        (redirect) => {
            // Arrange: redirect is the value from the URL

            // Act
            const result = safeRedirect(redirect);

            // Assert
            expect(result).toBeNull();
        },
    );

    /**
     * No redirect, or more than one ?redirect= (which Vue Router gives as an array), is ignored (boundary)
     */
    it.each([undefined, null, '', ['/a', '/b']])('ignores %j', (redirect) => {
        // Arrange: redirect is the value from the URL

        // Act
        const result = safeRedirect(redirect);

        // Assert
        expect(result).toBeNull();
    });
});
