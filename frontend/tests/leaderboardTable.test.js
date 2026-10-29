// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LeaderboardTable from '../components/tables/leaderboardTable.vue';
import { getLeaderboard } from '../services/leaderboardService';

vi.mock('../services/leaderboardService', () => ({
    getLeaderboard: vi.fn(),
}));

const subjects = [
    { subjectID: 'cyber', subjectName: 'Cyber Security' },
    { subjectID: 'web', subjectName: 'Web Apps' },
];

const overall = {
    subjects,
    students: [
        { rank: 1, name: 'Sam G.', isCurrentStudent: false, testsTaken: 3, averageScore: 90, points: 500 },
        { rank: 2, name: 'Alex B.', isCurrentStudent: true, testsTaken: 2, averageScore: 72.5, points: 290 },
    ],
};

const webApps = {
    subjects,
    students: [{ rank: 1, name: 'Jo W.', isCurrentStudent: false, testsTaken: 1, averageScore: 80, points: 200 }],
};

describe('LeaderboardTable', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('loads the overall leaderboard and lists the cohort subjects in the filter', async () => {
        getLeaderboard.mockResolvedValue(overall);

        const wrapper = mount(LeaderboardTable);
        expect(wrapper.get('[role="status"]').text()).toBe('Loading leaderboard...');
        await flushPromises();

        expect(getLeaderboard).toHaveBeenCalledWith('');
        expect(wrapper.findAll('option').map((option) => option.text())).toEqual([
            'Overall',
            'Cyber Security',
            'Web Apps',
        ]);

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Sam G.');
        expect(rows[0].text()).toContain('500');
        expect(rows[1].text()).toContain('72.5%');
    });

    it('highlights the logged in student', async () => {
        getLeaderboard.mockResolvedValue(overall);

        const wrapper = mount(LeaderboardTable);
        await flushPromises();

        const rows = wrapper.findAll('tbody tr');
        expect(rows[1].classes()).toContain('leaderboardCurrentStudent');
        expect(rows[1].text()).toContain('You');
        expect(rows[0].classes()).not.toContain('leaderboardCurrentStudent');
    });

    it('reloads the table for the chosen subject', async () => {
        getLeaderboard.mockResolvedValueOnce(overall).mockResolvedValueOnce(webApps);

        const wrapper = mount(LeaderboardTable);
        await flushPromises();

        await wrapper.get('select').setValue('web');
        await flushPromises();

        expect(getLeaderboard).toHaveBeenLastCalledWith('web');
        expect(wrapper.text()).toContain('Ranked by points earned on Web Apps tests.');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        expect(wrapper.get('tbody').text()).toContain('Jo W.');
    });

    it('ignores a slow response for a subject the student has moved away from', async () => {
        let resolveWebApps;
        getLeaderboard
            .mockResolvedValueOnce(overall)
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolveWebApps = resolve;
                }),
            )
            .mockResolvedValueOnce(overall);

        const wrapper = mount(LeaderboardTable);
        await flushPromises();

        await wrapper.get('select').setValue('web');
        await wrapper.get('select').setValue('');
        await flushPromises();

        resolveWebApps(webApps);
        await flushPromises();

        expect(wrapper.get('tbody').text()).toContain('Sam G.');
        expect(wrapper.get('tbody').text()).not.toContain('Jo W.');
    });

    it('shows a message when nobody has points yet', async () => {
        getLeaderboard.mockResolvedValue({ subjects, students: [] });

        const wrapper = mount(LeaderboardTable);
        await flushPromises();

        expect(wrapper.find('table').exists()).toBe(false);
        expect(wrapper.text()).toContain('No one has completed a test here yet.');
    });

    it('shows the error from the API when loading fails', async () => {
        getLeaderboard.mockRejectedValue({ response: { data: { error: 'This subject is not available.' } } });

        const wrapper = mount(LeaderboardTable);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('This subject is not available.');
    });
});
