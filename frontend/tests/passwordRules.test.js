import { describe, expect, it } from 'vitest';
import { meetsPasswordRules } from '../utils/passwordRules';

// Tests for meetsPasswordRules(), the password check shared by every page that sets a password
// each test is laid out Arrange, Act, Assert
describe('meetsPasswordRules', () => {
    /**
     * A password with all four kinds of character and 8 or more characters is accepted (normal)
     */
    it.each(['Password1!', 'Abcdef1#', 'a Longer Pass 9?'])('accepts %s', (password) => {
        // Arrange: password is what was typed

        // Act
        const result = meetsPasswordRules(password);

        // Assert
        expect(result).toBe(true);
    });

    /**
     * Exactly 8 characters is enough, 7 is not (boundary)
     */
    it.each([
        ['Abcde1!x', true],
        ['Abcd1!x', false],
    ])('checks the length of %s', (password, expected) => {
        // Arrange: password is what was typed

        // Act
        const result = meetsPasswordRules(password);

        // Assert
        expect(result).toBe(expected);
    });

    /**
     * Missing any one kind of character, or using a space as the symbol, is turned away like PHP does (error)
     */
    it.each(['password1!', 'PASSWORD1!', 'Password!!', 'Password12', 'Abcdefg1 ', '', undefined])(
        'turns away %s',
        (password) => {
            // Arrange: password is what was typed

            // Act
            const result = meetsPasswordRules(password);

            // Assert
            expect(result).toBe(false);
        },
    );
});
