/**
 * Column sorting for the tables that don't use DataTables, i.e. the cohort tables.
 * Clicking a heading goes ascending, descending, then back to the original order, the same as DataTables.
 */

const NEXT_DIRECTION = { '': 'asc', asc: 'desc', desc: '' };

/**
 * The sort after a heading is clicked
 * @param sort the current sort, i.e. { key: 'name', direction: 'asc' }
 * @param key the column that was clicked
 * @returns the new sort, with an empty key and direction when it's back to the original order
 */
export function nextSort(sort, key) {
    const direction = sort.key === key ? NEXT_DIRECTION[sort.direction] : 'asc';

    return { key: direction ? key : '', direction };
}

/**
 * A sorted copy of the rows, the original list is left alone
 * @param rows the rows in their original order
 * @param sort the current sort, i.e. { key: 'name', direction: 'asc' }
 * @param sortValues for each column, a function that picks the value to sort a row by
 */
export function sortRows(rows, sort, sortValues) {
    if (!sort.direction) {
        return rows;
    }

    const sortValue = sortValues[sort.key];
    const sign = sort.direction === 'asc' ? 1 : -1;

    return [...rows].sort((a, b) => sign * compareValues(sortValue(a), sortValue(b)));
}

// numbers by size, everything else as text, so WS10 comes after WS9
function compareValues(a, b) {
    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    return String(a ?? '').localeCompare(String(b ?? ''), 'en-GB', { numeric: true, sensitivity: 'base' });
}
