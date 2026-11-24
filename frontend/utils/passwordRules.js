// The password rules every page checks, the same ones PHP checks in Student::validPassword
// kept in one place so a change to the rules only needs making here

export const PASSWORD_RULES =
    'Use at least 8 characters, with an uppercase letter, a lowercase letter, a number and a symbol';

// a space doesn't count as the symbol, because PHP doesn't count it either
export function meetsPasswordRules(password) {
    return (
        typeof password === 'string' &&
        password.length >= 8 &&
        /[A-Z]/.test(password) &&
        /[a-z]/.test(password) &&
        /\d/.test(password) &&
        /[^A-Za-z0-9\s]/.test(password)
    );
}
