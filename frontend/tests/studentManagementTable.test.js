// @vitest-environment jsdom

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import StudentManagementTable from '../components/tables/studentManagementTable.vue';
import { getLecturerStudents } from '../services/studentService';

vi.mock('../services/studentService', () => ({
    getLecturerStudents: vi.fn(),
}));

vi.mock('datatables.net-vue3', async () => {
    const { defineComponent } = await import('vue');

    return {
        default: Object.assign(
            defineComponent({
                name: 'DataTable',
                props: ['data', 'columns', 'options'],
                template: `
                    <div>
                        <div
                            v-for="student in data"
                            :key="student.userID"
                            class="student-row"
                        >
                            {{ student.name }}
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
    default: {},
}));

describe('studentManagementTable.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
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

    it('renders the correct status badges for students', async () => {
        // Arrange
        getLecturerStudents.mockResolvedValue([]);

        // Act
        const wrapper = mount(StudentManagementTable);
        await flushPromises();

        const table = wrapper.findComponent({
            name: 'DataTable',
        });

        const columns = table.props('columns');
        const statusColumn = columns.find((column) => column.data === 'status');

        // Assert
        expect(statusColumn).toBeDefined();

        expect(statusColumn.render('Active', 'display')).toContain('studentStatusBadge');

        expect(statusColumn.render('Pending', 'display')).toContain('Pending');

        expect(statusColumn.render('Disabled', 'display')).toContain('Disabled');

        wrapper.unmount();
    });
});
