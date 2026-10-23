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
        const details = { email: 'ws123456@weston.ac.uk', isEmailVerified: false };
        api.get.mockResolvedValue({ data: details });

        await expect(getPendingVerification()).resolves.toEqual(details);
        expect(api.get).toHaveBeenCalledWith('/pending-verification');
    });

    it('asks for the link to be sent again', async () => {
        api.post.mockResolvedValue({ data: { message: 'We have sent a new link' } });

        await resendVerification();
        expect(api.post).toHaveBeenCalledWith('/resend-verification');
    });

    it('sends the corrected address in the request body', async () => {
        api.post.mockResolvedValue({ data: { email: 'ws123456@weston.ac.uk' } });

        await changeEmail('ws123456@weston.ac.uk');
        expect(api.post).toHaveBeenCalledWith('/change-email', { email: 'ws123456@weston.ac.uk' });
    });

    it('sends the link code in the request body rather than the URL', async () => {
        api.post.mockResolvedValue({ data: { message: 'Your email address has been verified' } });

        await verifyEmail('abc123');
        expect(api.post).toHaveBeenCalledWith('/verify-email', { token: 'abc123' });
    });
});
