// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import BackupCodesForm from '../components/forms/backupCodesForm.vue';

/**
 * Tests for the backup codes card, shown once after an MFA login
 * every test is laid out Arrange, Act, Assert
 */

const CODES = ['K7QM-2XPA', 'B3RT-9WEH'];

describe('BackupCodesForm', () => {
    /**
     * Continue without ticking the box shows the problem and stays on the codes (error)
     */
    it('needs the box ticking before carrying on', async () => {
        // Arrange
        const wrapper = mount(BackupCodesForm, { props: { codes: CODES } });

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toContain(
            'Tick the box to confirm you have saved your backup codes',
        );
        expect(wrapper.emitted('done')).toBeUndefined();
    });

    /**
     * Once the box is ticked Continue lets them go to their dashboard (normal)
     */
    it('carries on once the box is ticked', async () => {
        // Arrange
        const wrapper = mount(BackupCodesForm, { props: { codes: CODES } });

        // Act
        await wrapper.get('#savedCodes').setValue(true);
        await wrapper.get('form').trigger('submit');

        // Assert
        expect(wrapper.text()).toContain('K7QM-2XPA');
        expect(wrapper.emitted('done')).toHaveLength(1);
    });
});
