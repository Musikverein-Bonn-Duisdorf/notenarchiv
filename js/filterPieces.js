/**
 * List filter for Archiv entity lists (#Liste .list-row).
 * Uses data-search / data-sort-* via listRowSearchText when available.
 * AND-tokens via listRowMatchesQuery (UI-SHELL).
 */
function filterPieces() {
    var input = document.getElementById('filterString');
    var filter = input && input.value ? String(input.value) : '';
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
        var match = (typeof listRowMatchesQuery === 'function')
            ? listRowMatchesQuery(text, filter)
            : (!String(filter).trim() || String(text).toUpperCase().indexOf(String(filter).trim().toUpperCase()) > -1);
        if (match) {
            row.classList.remove('list-filtered-out');
        } else {
            row.classList.add('list-filtered-out');
        }
    }

    var sections = list.querySelectorAll('.collection-section');
    for (var s = 0; s < sections.length; s++) {
        var section = sections[s];
        var visible = section.querySelectorAll('.list-row:not(.list-filtered-out)');
        var nameText = section.getAttribute('data-search') || '';
        var nameHit = (typeof listRowMatchesQuery === 'function')
            ? listRowMatchesQuery(nameText, filter)
            : (!String(filter).trim() || String(nameText).toUpperCase().indexOf(String(filter).trim().toUpperCase()) > -1);
        if (visible.length > 0 || nameHit) {
            section.classList.remove('list-filtered-out');
        } else {
            section.classList.add('list-filtered-out');
        }
    }
}
