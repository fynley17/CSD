/**
 * Options for the filter dropdowns above the tables, kept together so every page words them the same.
 * The first option of each list shows everything.
 */

export const lecturerStatusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'disabled', label: 'Disabled' },
];

export const subjectStatusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
];

export const cohortStatusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'disabled', label: 'Disabled' },
];

export const studentStatusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'Active', label: 'Active' },
    { value: 'Pending', label: 'Pending' },
    { value: 'Disabled', label: 'Disabled' },
];

/**
 * Options for a list of names, i.e. subjects or cohorts, with the "All ..." one first
 * @param names the names to pick from, i.e. ['Databases', 'Networking']
 * @param allLabel the first option, i.e. 'All subjects'
 */
export function nameOptions(names, allLabel) {
    return [{ value: '', label: allLabel }, ...names.map((name) => ({ value: name, label: name }))];
}
