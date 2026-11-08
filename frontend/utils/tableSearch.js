/**
 * The search box for a DataTables table lives in the toolbar above it, next to the filter dropdowns,
 * so DataTables' own box is hidden and the toolbar's text is passed to it instead.
 */

// no page length or search box above the table, the info text and page buttons below stay
export const toolbarLayout = { topStart: null, topEnd: null };

/**
 * Searches the table for the toolbar's text, the same as typing in DataTables' own box
 * @param dataTable the DataTable component's ref, null until the table has loaded
 * @param text what was typed in the toolbar's search box
 */
export function searchTable(dataTable, text) {
    dataTable?.dt?.search(text).draw();
}
