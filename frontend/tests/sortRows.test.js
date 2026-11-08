// Tests for the column sorting used by the cohort tables
import { describe, expect, it } from 'vitest';
import { nextSort, sortRows } from '../utils/sortRows';

describe('nextSort', () => {
    /**
     * Clicking the same heading goes up, down, then off (normal)
     */
    it('goes ascending, descending, then back to no sort', () => {
        // Arrange
        let sort = { key: '', direction: '' };

        // Act and Assert
        sort = nextSort(sort, 'name');
        expect(sort).toEqual({ key: 'name', direction: 'asc' });
        sort = nextSort(sort, 'name');
        expect(sort).toEqual({ key: 'name', direction: 'desc' });
        sort = nextSort(sort, 'name');
        expect(sort).toEqual({ key: '', direction: '' });
    });

    /**
     * A different heading always starts ascending (edge)
     */
    it('starts ascending when a new column is clicked', () => {
        // Act
        const sort = nextSort({ key: 'name', direction: 'desc' }, 'email');

        // Assert
        expect(sort).toEqual({ key: 'email', direction: 'asc' });
    });
});

describe('sortRows', () => {
    const sortValues = { number: (row) => row.number, count: (row) => row.count };
    const rows = [
        { number: 'WS10', count: 2 },
        { number: 'WS9', count: 10 },
    ];

    /**
     * Numbers inside text sort by size, so WS9 comes before WS10 (normal)
     */
    it('sorts text with numbers in it naturally', () => {
        // Act
        const sorted = sortRows(rows, { key: 'number', direction: 'asc' }, sortValues);

        // Assert
        expect(sorted.map((row) => row.number)).toEqual(['WS9', 'WS10']);
    });

    /**
     * Counts sort as numbers, so 10 comes after 2 (normal)
     */
    it('sorts numbers by size, largest first when descending', () => {
        // Act
        const sorted = sortRows(rows, { key: 'count', direction: 'desc' }, sortValues);

        // Assert
        expect(sorted.map((row) => row.count)).toEqual([10, 2]);
    });

    /**
     * Sorting never changes the list it was given (edge)
     */
    it('leaves the original rows in their order', () => {
        // Act
        sortRows(rows, { key: 'number', direction: 'asc' }, sortValues);

        // Assert
        expect(rows.map((row) => row.number)).toEqual(['WS10', 'WS9']);
    });
});
