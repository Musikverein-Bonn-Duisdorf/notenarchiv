/**
 * List filter for Archiv entity lists (#Liste .list-row).
 * Uses data-search / data-sort-* via listRowSearchText when available.
 */
function filterPieces() {
    var input = document.getElementById('filterString');
    var filter = input && input.value ? String(input.value).trim().toUpperCase() : '';
    var list = document.getElementById('Liste');
    if (!list) {
        return;
    }

    var rows = list.querySelectorAll('.list-row');
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var text = (typeof listRowSearchText === 'function')
            ? listRowSearchText(row)
            : (row.getAttribute('data-search') || row.textContent || '');
        text = String(text).toUpperCase();
        if (!filter || text.indexOf(filter) > -1) {
            row.classList.remove('list-filtered-out');
        } else {
            row.classList.add('list-filtered-out');
        }
    }

    var sections = list.querySelectorAll('.collection-section');
    for (var s = 0; s < sections.length; s++) {
        var section = sections[s];
        var visible = section.querySelectorAll('.list-row:not(.list-filtered-out)');
        var nameHit = !filter
            || String(section.getAttribute('data-search') || '').toUpperCase().indexOf(filter) > -1;
        if (visible.length > 0 || nameHit) {
            section.classList.remove('list-filtered-out');
        } else {
            section.classList.add('list-filtered-out');
        }
    }
}
