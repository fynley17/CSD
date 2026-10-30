// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PointsLineChart from '../components/common/pointsLineChart.vue';

// jsdom has no canvas to draw on, so these check what's handed to the chart
vi.mock('vue-chartjs', () => ({
    Line: { name: 'Line', props: ['data', 'options'], template: '<canvas />' },
}));

const points = [
    { date: '2026-09-02', testName: 'Test 1', totalPoints: 8 },
    { date: '2026-09-09', testName: 'Test 2', totalPoints: 15 },
    { date: '2026-09-14', testName: 'Test 3', totalPoints: 85 },
];

describe('pointsLineChart', () => {
    it('shows a message instead of a graph when there are no tests', () => {
        const wrapper = mount(PointsLineChart, { props: { points: [] } });

        expect(wrapper.findComponent({ name: 'Line' }).exists()).toBe(false);
        expect(wrapper.text()).toContain('Complete a test');
    });

    it('passes the dates and running totals to the chart', () => {
        const wrapper = mount(PointsLineChart, { props: { points } });
        const { data } = wrapper.findComponent({ name: 'Line' }).props();

        expect(data.labels).toEqual(['2 Sept', '9 Sept', '14 Sept']);
        expect(data.datasets[0].data).toEqual([8, 15, 85]);
    });

    it('shows the test name and points in the tooltip', () => {
        const wrapper = mount(PointsLineChart, { props: { points } });
        const { callbacks } = wrapper.findComponent({ name: 'Line' }).props().options.plugins.tooltip;

        expect(callbacks.title([{ dataIndex: 1 }])).toBe('Test 2');
        expect(callbacks.label({ parsed: { y: 15 } })).toBe('15 points');
    });
});
