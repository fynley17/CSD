// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CompletedTestItem from '../components/common/completedTestItem.vue';
import { getCompletedTestReview } from '../services/resultService';
import { exportTestPdf } from '../utils/exportTestPdf';

vi.mock('../services/resultService', () => ({
    getCompletedTestReview: vi.fn(),
}));

vi.mock('../utils/exportTestPdf', () => ({
    exportTestPdf: vi.fn(),
}));

const baseResult = {
    completedID: 'completed-1',
    testID: 'test-1',
    testName: 'Web Apps Fundamentals',
    testDescription: 'Review core web application concepts.',
    subjectName: 'Web Apps',
    completedDate: '2026-10-20 09:00:00',
    questionCount: 15,
    pointsEarned: 390,
    percentageCorrect: 86.67,
};

describe('CompletedTestItem', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('displays the completed test information and percentage', () => {
        const wrapper = mount(CompletedTestItem, { props: { result: baseResult } });

        expect(wrapper.get('.completedTestTitle').text()).toBe(baseResult.testName);
        expect(wrapper.get('.completedTestDescription').text()).toBe(baseResult.testDescription);
        expect(wrapper.get('.completedTestPercentage').text()).toBe('86.7%');

        const details = wrapper.findAll('.completedTestDetails dt').map((element) => element.text());
        expect(details).toEqual(['Subject', 'Completed', 'Questions', 'Points scored']);

        const values = wrapper.findAll('.completedTestDetails dd').map((element) => element.text());
        expect(values).toEqual([
            'Web Apps',
            new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(baseResult.completedDate)),
            '15',
            '390',
        ]);
    });

    it('shows whole percentages without a decimal place', () => {
        const wrapper = mount(CompletedTestItem, {
            props: { result: { ...baseResult, percentageCorrect: 100 } },
        });

        expect(wrapper.get('.completedTestPercentage').text()).toBe('100%');
    });

    it('loads the attempt and exports it as a PDF', async () => {
        const review = { testName: baseResult.testName, studentName: 'Ada Lovelace', questions: [] };
        getCompletedTestReview.mockResolvedValue(review);
        const wrapper = mount(CompletedTestItem, { props: { result: baseResult } });

        await wrapper.get('.completedTestExport').trigger('click');
        await flushPromises();

        expect(getCompletedTestReview).toHaveBeenCalledWith('completed-1');
        expect(exportTestPdf).toHaveBeenCalledWith(review);
        expect(wrapper.find('.completedTestExportError').exists()).toBe(false);
    });

    it('shows the error when the export fails', async () => {
        getCompletedTestReview.mockRejectedValue({
            response: { data: { error: 'This completed test could not be found.' } },
        });
        const wrapper = mount(CompletedTestItem, { props: { result: baseResult } });

        await wrapper.get('.completedTestExport').trigger('click');
        await flushPromises();

        expect(exportTestPdf).not.toHaveBeenCalled();
        expect(wrapper.get('.completedTestExportError').text()).toBe('This completed test could not be found.');
    });
});
