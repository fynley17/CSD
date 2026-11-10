// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TestManagementTable from '../components/tables/testManagementTable.vue';
import { getLecturerTests } from '../services/testService';

// Mock the DataTable component so we can test the column output without
// relying on DataTables' browser-specific functionality.
vi.mock('datatables.net-vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const DataTable = defineComponent({
        name: 'DataTable',
        props: {
            columns: Array,
            data: Array,
            options: Object,
        },
        setup(props) {
            return () =>
                h('table', [
                    h('thead', [
                        h(
                            'tr',
                            props.columns.map((column) => h('th', column.title)),
                        ),
                    ]),
                    h(
                        'tbody',
                        (props.data ?? []).map((row) =>
                            h(
                                'tr',
                                {
                                    key: row.testID,
                                },
                                props.columns.map((column) => {
                                    const value = column.data ? row[column.data] : null;

                                    const displayValue =
                                        typeof column.render === 'function'
                                            ? column.render(value, 'display', row)
                                            : value;

                                    return h('td', String(displayValue ?? ''));
                                }),
                            ),
                        ),
                    ),
                ]);
        },
    });

    DataTable.use = vi.fn();

    return { default: DataTable };
});

// Provide the DataTables text renderer used by the component.
vi.mock('datatables.net-bs5', () => ({
    default: {
        render: {
            text: () => (value) => value,
        },
    },
}));

vi.mock('../services/testService', () => ({
    getLecturerTests: vi.fn(),
}));

const tests = [
    {
        testID: 'test-1',
        testName: 'Random Test',
        subjectName: 'Mathematics',
        questionCount: 10,
        timeLimit: 30,
        questionSelectionMethod: 'random',
        isActive: true,
    },
    {
        testID: 'test-2',
        testName: 'Personalised Test',
        subjectName: 'Biology',
        questionCount: 15,
        timeLimit: 45,
        questionSelectionMethod: 'frequently_incorrect',
        isActive: true,
    },
    {
        testID: 'test-3',
        testName: 'Older Test',
        subjectName: 'Chemistry',
        questionCount: 10,
        timeLimit: null,
        isActive: true,
    },
];

describe('TestManagementTable', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(getLecturerTests).mockResolvedValue(tests);
    });

    async function mountTable() {
        const wrapper = mount(TestManagementTable);
        await flushPromises();

        return wrapper;
    }

    it('displays the Question Selection column', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('Question Selection');
    });

    it('displays Random for tests using random selection', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('Random');
    });

    it('displays Frequently incorrect for personalised tests', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('Frequently incorrect');
    });

    it('defaults to Random when the selection method is missing', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('Older Test');
        expect(wrapper.text()).toContain('Random');
    });

    it('retrieves lecturer tests when the table loads', async () => {
        // Arrange
        await mountTable();

        // Assert
        expect(getLecturerTests).toHaveBeenCalledOnce();
    });
});
