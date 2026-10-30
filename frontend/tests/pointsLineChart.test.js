// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PointsLineChart from '../components/common/pointsLineChart.vue';

// jsdom has no canvas to draw on, so these check what's handed to the chart
vi.mock('vue-chartjs', () => ({
    Line: { name: 'Line', props: ['data', 'options'], template: '<canvas />' },
}));

// the totals run on from before each time period, so filtering must not restart them
const points = [
    { date: '2025-06-01 09:00:00', testName: 'Old test', totalPoints: 700 },
    { date: '2026-08-20 09:00:00', testName: 'Summer test', totalPoints: 730 },
    { date: '2026-10-10 09:00:00', testName: 'Month test', totalPoints: 760 },
    { date: '2026-10-28 09:00:00', testName: 'Week test', totalPoints: 800 },
];

function chartValues(wrapper) {
    return wrapper
        .findComponent({ name: 'Line' })
        .props()
        .data.datasets[0].data.map((point) => point.y);
}

function selectRange(wrapper, label) {
    return wrapper
        .findAll('button')
        .find((button) => button.text() === label)
        .trigger('click');
}

describe('pointsLineChart', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-30T12:00:00'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows a message and no time buttons when there are no tests', () => {
        const wrapper = mount(PointsLineChart, { props: { points: [] } });

        expect(wrapper.findComponent({ name: 'Line' }).exists()).toBe(false);
        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.text()).toContain('Complete a test');
    });

    it('starts on all time with every test', () => {
        const wrapper = mount(PointsLineChart, { props: { points } });
        const { options } = wrapper.findComponent({ name: 'Line' }).props();

        expect(chartValues(wrapper)).toEqual([700, 730, 760, 800]);
        expect(options.scales.x.min).toBeUndefined();
        expect(options.scales.y.beginAtZero).toBe(true);
        expect(wrapper.find('button[aria-pressed="true"]').text()).toBe('All time');
    });

    it.each([
        ['Week', [800]],
        ['Month', [760, 800]],
        ['3 months', [730, 760, 800]],
        ['Year', [730, 760, 800]],
        ['All time', [700, 730, 760, 800]],
    ])('%s only shows its tests and keeps their running totals', async (label, expected) => {
        const wrapper = mount(PointsLineChart, { props: { points } });

        await selectRange(wrapper, label);

        expect(chartValues(wrapper)).toEqual(expected);
        expect(wrapper.find('button[aria-pressed="true"]').text()).toBe(label);
    });

    it('stops forcing the y axis to start at zero on a shorter period', async () => {
        const wrapper = mount(PointsLineChart, { props: { points } });

        await selectRange(wrapper, 'Week');
        const { y } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        expect(y.beginAtZero).toBe(false);
    });

    it.each([
        ['Week', '2026-10-24', 'day', undefined],
        ['Month', '2026-09-30', 'week', undefined],
        ['3 months', '2026-07-30', 'week', 2],
        ['Year', '2025-10-30', 'month', undefined],
    ])('sets the x axis to whole days and clean labels for %s', async (label, start, unit, stepSize) => {
        const wrapper = mount(PointsLineChart, { props: { points } });

        await selectRange(wrapper, label);
        const { x } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        expect(x.min).toBe(new Date(`${start}T00:00:00`).getTime());
        expect(x.max).toBe(new Date('2026-10-30T23:59:59.999').getTime());
        expect(x.time.unit).toBe(unit);
        expect(x.ticks.stepSize).toBe(stepSize);
    });

    it('keeps a month ago in the right month at the end of a long month', async () => {
        vi.setSystemTime(new Date('2026-10-31T12:00:00'));
        const wrapper = mount(PointsLineChart, { props: { points } });

        await selectRange(wrapper, 'Month');
        const { x } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        expect(x.min).toBe(new Date('2026-09-30T00:00:00').getTime());
    });

    it('never labels all time in hours', () => {
        const wrapper = mount(PointsLineChart, { props: { points } });
        const { x } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        expect(x.time.minUnit).toBe('day');
    });

    it('says so when a period has no tests, and can go back to the graph', async () => {
        const wrapper = mount(PointsLineChart, { props: { points: [points[0]] } });

        await selectRange(wrapper, 'Week');
        expect(wrapper.findComponent({ name: 'Line' }).exists()).toBe(false);
        expect(wrapper.text()).toContain('No tests in the last week.');

        await selectRange(wrapper, 'All time');
        expect(chartValues(wrapper)).toEqual([700]);
    });

    it('shows the test name, points and date in the tooltip for the selected period', async () => {
        const wrapper = mount(PointsLineChart, { props: { points } });

        await selectRange(wrapper, 'Month');
        const { callbacks } = wrapper.findComponent({ name: 'Line' }).props().options.plugins.tooltip;

        expect(callbacks.title([{ dataIndex: 1 }])).toBe('Week test');
        expect(callbacks.label({ parsed: { y: 800 } })).toBe('800 points');
        expect(callbacks.afterLabel({ dataIndex: 1 })).toBe('28 Oct 2026');
    });
});
