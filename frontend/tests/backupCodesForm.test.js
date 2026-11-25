// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import BackupCodesForm from '../components/forms/backupCodesForm.vue';
import { saveBackupCodes } from '../services/authService';

vi.mock('../services/authService', () => ({
    saveBackupCodes: vi.fn(),
}));

// jsdom cant make files, fake it
URL.createObjectURL = vi.fn(() => 'blob:codes');
URL.revokeObjectURL = vi.fn();

/**
 * Tests for the backup codes card, shown once after an MFA login
 * every test is laid out Arrange, Act, Assert
 */

const CODES = ['K7QM-2XPA', 'B3RT-9WEH'];

// presses the Download codes button
async function downloadCodes(wrapper) {
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Download codes')
        .trigger('click');
}

describe('BackupCodesForm', () => {
    /**
     * Continue without copy/download, then without the tick, stays put (error)
     */
    it('needs the codes copying or downloading and the box ticking', async () => {
        // Arrange
        const wrapper = mount(BackupCodesForm, { props: { codes: CODES } });

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        const firstProblem = wrapper.get('[role="alert"]').text();
        await downloadCodes(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(firstProblem).toContain('Copy or download your backup codes before you continue');
        expect(wrapper.get('[role="alert"]').text()).toContain(
            'Tick the box to confirm you have saved your backup codes',
        );
        expect(wrapper.emitted('done')).toBeUndefined();
    });

    /**
     * Downloaded and ticked, codes get saved and they carry on (normal)
     */
    it('saves the codes and carries on once both are done', async () => {
        // Arrange
        saveBackupCodes.mockResolvedValue({ message: 'Backup codes saved' });
        const wrapper = mount(BackupCodesForm, { props: { codes: CODES } });

        // Act
        await downloadCodes(wrapper);
        await wrapper.get('#savedCodes').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('K7QM-2XPA');
        expect(saveBackupCodes).toHaveBeenCalled();
        expect(wrapper.emitted('done')).toHaveLength(1);
    });
});
