// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import GradesLineChart from '../components/common/gradesLineChart.vue';

// jsdom has no canvas to draw on, so these check what's handed to the chart
vi.mock('vue-chartjs', () => ({
    Line: { name: 'Line', props: ['data', 'options'], template: '<canvas />' },
}));

const grades = [
    { date: '2025-06-01 09:00:00', testName: 'Old test', percentage: 40 },
    { date: '2026-08-20 09:00:00', testName: 'Summer test', percentage: 65.5 },
    { date: '2026-10-10 09:00:00', testName: 'Month test', percentage: 50 },
    { date: '2026-10-28 09:00:00', testName: 'Week test', percentage: 90 },
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

describe('gradesLineChart', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-30T12:00:00'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows a message and no time buttons when there are no tests', () => {
        // Arrange
        const noGrades = [];

        // Act
        const wrapper = mount(GradesLineChart, { props: { grades: noGrades } });

        // Assert
        expect(wrapper.findComponent({ name: 'Line' }).exists()).toBe(false);
        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.text()).toContain('Complete a test');
    });

    it('starts on all time with every test', () => {
        // Arrange: grades holds the four completed tests

        // Act
        const wrapper = mount(GradesLineChart, { props: { grades } });
        const { options } = wrapper.findComponent({ name: 'Line' }).props();

        // Assert
        expect(chartValues(wrapper)).toEqual([40, 65.5, 50, 90]);
        expect(options.scales.x.min).toBeUndefined();
        expect(wrapper.find('button[aria-pressed="true"]').text()).toBe('All time');
    });

    it.each([
        ['Week', [90]],
        ['Month', [50, 90]],
        ['3 months', [65.5, 50, 90]],
        ['Year', [65.5, 50, 90]],
        ['All time', [40, 65.5, 50, 90]],
    ])('%s only shows its tests', async (label, expected) => {
        // Arrange
        const wrapper = mount(GradesLineChart, { props: { grades } });

        // Act
        await selectRange(wrapper, label);

        // Assert
        expect(chartValues(wrapper)).toEqual(expected);
        expect(wrapper.find('button[aria-pressed="true"]').text()).toBe(label);
    });

    it.each(['Week', 'All time'])('keeps the y axis at 0 to 100%% on %s', async (label) => {
        // Arrange
        const wrapper = mount(GradesLineChart, { props: { grades } });

        // Act
        await selectRange(wrapper, label);
        const { y } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        // Assert
        expect(y.min).toBe(0);
        expect(y.max).toBe(100);
        expect(y.ticks.callback(80)).toBe('80%');
    });

    it.each([
        ['Week', '2026-10-24', 'day', undefined],
        ['Month', '2026-09-30', 'week', undefined],
        ['3 months', '2026-07-30', 'week', 2],
        ['Year', '2025-10-30', 'month', undefined],
    ])('sets the x axis to whole days and clean labels for %s', async (label, start, unit, stepSize) => {
        // Arrange
        const wrapper = mount(GradesLineChart, { props: { grades } });

        // Act
        await selectRange(wrapper, label);
        const { x } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        // Assert
        expect(x.min).toBe(new Date(`${start}T00:00:00`).getTime());
        expect(x.max).toBe(new Date('2026-10-30T23:59:59.999').getTime());
        expect(x.time.unit).toBe(unit);
        expect(x.ticks.stepSize).toBe(stepSize);
    });

    it('keeps a month ago in the right month at the end of a long month', async () => {
        // Arrange
        vi.setSystemTime(new Date('2026-10-31T12:00:00'));
        const wrapper = mount(GradesLineChart, { props: { grades } });

        // Act
        await selectRange(wrapper, 'Month');
        const { x } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        // Assert
        expect(x.min).toBe(new Date('2026-09-30T00:00:00').getTime());
    });

    it('never labels all time in hours', () => {
        // Arrange: grades holds the four completed tests

        // Act
        const wrapper = mount(GradesLineChart, { props: { grades } });
        const { x } = wrapper.findComponent({ name: 'Line' }).props().options.scales;

        // Assert
        expect(x.time.minUnit).toBe('day');
    });

    it.each(['Week', 'Month', '3 months', 'Year', 'All time'])(
        'has the year in the x axis labels for %s',
        async (label) => {
            // Arrange
            const wrapper = mount(GradesLineChart, { props: { grades } });

            // Act
            await selectRange(wrapper, label);
            const { displayFormats } = wrapper.findComponent({ name: 'Line' }).props().options.scales.x.time;

            // Assert
            expect(Object.values(displayFormats).every((format) => format.includes('yyyy'))).toBe(true);
        },
    );

    it('says so when a period has no tests, and can go back to the graph', async () => {
        // Arrange
        const wrapper = mount(GradesLineChart, { props: { grades: [grades[0]] } });

        // Act
        await selectRange(wrapper, 'Week');
        const hasChartOnWeek = wrapper.findComponent({ name: 'Line' }).exists();
        const textOnWeek = wrapper.text();

        await selectRange(wrapper, 'All time');

        // Assert
        expect(hasChartOnWeek).toBe(false);
        expect(textOnWeek).toContain('No tests in the last week.');
        expect(chartValues(wrapper)).toEqual([40]);
    });

    it('shows the test name, grade and date in the tooltip for the selected period', async () => {
        // Arrange
        const wrapper = mount(GradesLineChart, { props: { grades } });

        // Act
        await selectRange(wrapper, 'Month');
        const { callbacks } = wrapper.findComponent({ name: 'Line' }).props().options.plugins.tooltip;

        // Assert
        expect(callbacks.title([{ dataIndex: 1 }])).toBe('Week test');
        expect(callbacks.label({ parsed: { y: 90 } })).toBe('90%');
        expect(callbacks.afterLabel({ dataIndex: 1 })).toBe('28 Oct 2026');
    });
});
