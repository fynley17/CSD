// @vitest-environment jsdom

// Tests for the shared filter dropdown every table page uses
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TableFilter from '../components/tables/tableFilter.vue';

const options = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
];

describe('TableFilter', () => {
    /**
     * The label is tied to the dropdown, so screen readers and clicking the label both work (normal)
     */
    it('labels the dropdown and lists the options', () => {
        // Act
        const filter = mount(TableFilter, { props: { id: 'statusFilter', label: 'Status', options } });

        // Assert
        expect(filter.get('label').attributes('for')).toBe('statusFilter');
        expect(filter.get('select').attributes('id')).toBe('statusFilter');
        expect(filter.findAll('option').map((option) => option.text())).toEqual(['All statuses', 'Active']);
    });

    /**
     * Picking an option updates the page's value and says it changed (normal)
     */
    it('sends the picked value back to the page', async () => {
        // Arrange
        const filter = mount(TableFilter, { props: { id: 'statusFilter', label: 'Status', options } });

        // Act
        await filter.get('select').setValue('active');

        // Assert
        expect(filter.emitted('update:modelValue')).toEqual([['active']]);
        expect(filter.emitted('change')).toEqual([['active']]);
    });
});
