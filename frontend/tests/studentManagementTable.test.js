// @vitest-environment jsdom

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import StudentManagementTable from '../components/tables/studentManagementTable.vue';
import { getLecturerStudents, updateStudentStatus } from '../services/studentService';

const { tableSearch, tableDraw } = vi.hoisted(() => ({
    tableSearch: vi.fn(),
    tableDraw: vi.fn(),
}));

vi.mock('../services/studentService', () => ({
    getLecturerStudents: vi.fn(),
    updateStudentStatus: vi.fn(),
}));

// draws each row with the #status and #actions slots, like DataTables does in the browser
vi.mock('datatables.net-vue3', async () => {
    const { defineComponent } = await import('vue');

    return {
        default: Object.assign(
            defineComponent({
                name: 'DataTable',
                props: ['data', 'columns', 'options'],
                setup(_props, { expose }) {
                    expose({
                        dt: {
                            column(index) {
                                return {
                                    search(value, regex, smart) {
                                        tableSearch(index, value, regex, smart);
                                        return { draw: tableDraw };
                                    },
                                };
                            },
                        },
                    });
                },
                template: `
                    <div>
                        <div
                            v-for="student in data"
                            :key="student.userID"
                            class="student-row"
                        >
                            {{ student.name }}
                            <slot name="status" :cellData="student.status" :rowData="student" />
                            <slot name="actions" :cellData="null" :rowData="student" />
                        </div>
                    </div>
                `,
            }),
            {
                use: vi.fn(),
            },
        ),
    };
});

vi.mock('datatables.net-bs5', () => ({
    default: {
        render: { text: () => ({}) },
    },
}));

describe('studentManagementTable.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        HTMLDialogElement.prototype.showModal = vi.fn(function showModal() {
            this.setAttribute('open', '');
        });
        HTMLDialogElement.prototype.close = vi.fn(function close() {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        });
    });

    it('applies and clears the status search when the status prop changes', async () => {
        getLecturerStudents.mockResolvedValue([]);
        const wrapper = mount(StudentManagementTable);
        await flushPromises();
        tableSearch.mockClear();
        tableDraw.mockClear();

        await wrapper.setProps({ status: 'Pending' });
        await flushPromises();
        await wrapper.setProps({ status: '' });
        await flushPromises();

        expect(tableSearch.mock.calls).toEqual([
            [3, '^Pending$', true, false],
            [3, '', true, false],
        ]);
        expect(tableDraw).toHaveBeenCalledTimes(2);

        wrapper.unmount();
    });

    it('loads and displays students successfully', async () => {
        // Arrange
        const mockStudents = [
            {
                userID: 1,
                name: 'Alex Morgan',
                studentNumber: 'ws000001',
                email: 'alex@example.com',
                status: 'Active',
                lastLogin: '2026-10-01',
                cohort: 'Cohort A',
                subjects: 'English, Mathematics',
            },
            {
                userID: 2,
                name: 'Jamie Wilson',
                studentNumber: 'ws000002',
                email: 'jamie@example.com',
                status: 'Pending',
                lastLogin: null,
                cohort: 'Cohort B',
                subjects: 'Science',
            },
        ];

        getLecturerStudents.mockResolvedValue(mockStudents);

        // Act
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        // Assert
        expect(getLecturerStudents).toHaveBeenCalledOnce();
        expect(wrapper.text()).toContain('Alex Morgan');
        expect(wrapper.text()).toContain('Jamie Wilson');
        expect(wrapper.vm.cohorts).toEqual(['Cohort A', 'Cohort B']);

        wrapper.unmount();
    });

    it('displays an error when students cannot be loaded', async () => {
        // Arrange
        getLecturerStudents.mockRejectedValue(new Error('API request failed'));

        // Act
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('Unable to load students. Please try again later.');

        wrapper.unmount();
    });

    it('shows each status as the house style pill', async () => {
        // Arrange
        getLecturerStudents.mockResolvedValue([
            { userID: 1, name: 'Alex Morgan', status: 'Active' },
            { userID: 2, name: 'Jamie Wilson', status: 'Pending' },
            { userID: 3, name: 'Sam Lee', status: 'Disabled' },
        ]);

        // Act
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        // Assert
        const pills = wrapper.findAll('.statusPill');
        expect(pills.map((pill) => pill.text())).toEqual(['Active', 'Pending', 'Disabled']);
        expect(pills[0].classes()).toContain('statusActive');
        expect(pills[1].classes()).toContain('statusPending');
        expect(pills[2].classes()).toContain('statusDisabled');

        wrapper.unmount();
    });

    it('sorts and filters on the status word, not the pill', async () => {
        // Arrange
        getLecturerStudents.mockResolvedValue([]);

        // Act
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        const statusColumn = wrapper
            .findComponent({ name: 'DataTable' })
            .props('columns')
            .find((column) => column.data === 'status');

        // Assert
        expect(statusColumn.render.display).toBe('#status');
        expect(statusColumn.render._('Pending')).toBe('Pending');

        wrapper.unmount();
    });

    it('gives each status the right buttons, named for the student', async () => {
        // Arrange
        getLecturerStudents.mockResolvedValue([
            { userID: 1, name: 'Alex Morgan', status: 'Active' },
            { userID: 2, name: 'Jamie Wilson', status: 'Pending' },
            { userID: 3, name: 'Sam Lee', status: 'Disabled' },
        ]);

        // Act
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        const labels = wrapper.findAll('.student-row button').map((button) => button.attributes('aria-label'));

        // Assert
        expect(labels).toEqual([
            'Edit Alex Morgan',
            'Disable Alex Morgan',
            'Delete Alex Morgan',
            'Approve Jamie Wilson',
            'Decline Jamie Wilson',
            'Edit Sam Lee',
            'Enable Sam Lee',
            'Delete Sam Lee',
        ]);
        expect(wrapper.get('[aria-label="Delete Sam Lee"]').classes()).toContain('btn-outline-danger');
        expect(wrapper.get('[aria-label="Edit Sam Lee"]').classes()).toContain('btn-outline-primary');

        wrapper.unmount();
    });

    it('sends the student to the page when Edit is clicked', async () => {
        // Arrange
        const student = { userID: 1, name: 'Alex Morgan', status: 'Active' };
        getLecturerStudents.mockResolvedValue([student]);
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        // Act
        await wrapper.get('[aria-label="Edit Alex Morgan"]').trigger('click');

        // Assert
        expect(wrapper.emitted('edit')).toEqual([[student]]);

        wrapper.unmount();
    });

    it('does not disable a student until the confirmation is accepted', async () => {
        const student = { userID: 1, name: 'Alex Morgan', status: 'Active' };
        getLecturerStudents.mockResolvedValue([student]);
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        await wrapper.get('[aria-label="Disable Alex Morgan"]').trigger('click');
        await flushPromises();

        const dialog = wrapper.get('dialog[aria-label="Confirm Disable"]');
        expect(dialog.attributes('open')).toBeDefined();
        expect(dialog.text()).toContain('Alex Morgan');
        expect(updateStudentStatus).not.toHaveBeenCalled();

        await dialog.get('.btn-outline-primary').trigger('click');
        await flushPromises();

        expect(dialog.attributes('open')).toBeUndefined();
        expect(updateStudentStatus).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('disables a student after the confirmation is accepted', async () => {
        const student = { userID: 1, name: 'Alex Morgan', status: 'Active' };
        getLecturerStudents.mockResolvedValue([student]);
        updateStudentStatus.mockResolvedValue({});
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        await wrapper.get('[aria-label="Disable Alex Morgan"]').trigger('click');
        await flushPromises();
        await wrapper.get('dialog[aria-label="Confirm Disable"] .btn-danger').trigger('click');
        await flushPromises();

        expect(updateStudentStatus).toHaveBeenCalledWith(1, false);
        expect(getLecturerStudents).toHaveBeenCalledTimes(2);
        expect(wrapper.get('dialog[aria-label="Confirm Disable"]').attributes('open')).toBeUndefined();

        wrapper.unmount();
    });
});
