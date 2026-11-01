import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { changeEmail, getPendingVerification, resendVerification, verifyEmail } from '../services/verificationService';

vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe('verificationService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('asks which address is waiting to be verified', async () => {
        // Arrange
        const details = { email: 'ws123456@weston.ac.uk', isEmailVerified: false };
        api.get.mockResolvedValue({ data: details });

        // Act
        const response = await getPendingVerification();

        // Assert
        expect(response).toEqual(details);
        expect(api.get).toHaveBeenCalledWith('/pending-verification');
    });

    it('asks for the link to be sent again', async () => {
        // Arrange
        api.post.mockResolvedValue({ data: { message: 'We have sent a new link' } });

        // Act
        await resendVerification();

        // Assert
        expect(api.post).toHaveBeenCalledWith('/resend-verification');
    });

    it('sends the corrected address in the request body', async () => {
        // Arrange
        api.post.mockResolvedValue({ data: { email: 'ws123456@weston.ac.uk' } });

        // Act
        await changeEmail('ws123456@weston.ac.uk');

        // Assert
        expect(api.post).toHaveBeenCalledWith('/change-email', { email: 'ws123456@weston.ac.uk' });
    });

    it('sends the link code in the request body rather than the URL', async () => {
        // Arrange
        api.post.mockResolvedValue({ data: { message: 'Your email address has been verified' } });

        // Act
        await verifyEmail('abc123');

        // Assert
        expect(api.post).toHaveBeenCalledWith('/verify-email', { token: 'abc123' });
    });
});
