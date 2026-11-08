/**
 * DataTables initComplete for every table on the site.
 * On phones the table scrolls sideways inside .adminTable, so keyboard users need to be able to
 * focus it and use the arrow keys, and screen readers need a name for it.
 * @param label what a screen reader calls the scrolling area, i.e. "Lecturers table"
 */
export function tableScrollRegion(label) {
    return function initComplete() {
        const scroller = this.api().table().container().querySelector('.dt-layout-table');
        if (!scroller) {
            return;
        }
        scroller.setAttribute('tabindex', '0');
        scroller.setAttribute('role', 'region');
        scroller.setAttribute('aria-label', label);
    };
}
