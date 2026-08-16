/**
 * List filter for Archiv entity lists (#Liste .list-row).
 * Uses data-search / data-sort-* via listRowSearchText when available.
 * AND-tokens via listRowMatchesQuery (UI-SHELL).
 *
 * Collections (#Liste .collection-section):
 * - Name hit → show section, leave piece rows unfiltered.
 * - Piece hit only → show section, filter piece rows to matches.
 * - Flat lists (Stücke/Komponisten/…) keep row-only filtering.
 */
function filterPieces() {
    var input = document.getElementById('filterString');
    var filter = input && input.value ? String(input.value) : '';
    var list = document.getElementById('Liste');
    if (!list) {
        return;
    }

    function rowMatches(row) {
        var text = (typeof listRowSearchText === 'function')
            ? listRowSearchText(row)
            : (row.getAttribute('data-search') || row.textContent || '');
        return (typeof listRowMatchesQuery === 'function')
            ? listRowMatchesQuery(text, filter)
            : (!String(filter).trim() || String(text).toUpperCase().indexOf(String(filter).trim().toUpperCase()) > -1);
    }

    function setFilteredOut(el, hide) {
        if (hide) {
            el.classList.add('list-filtered-out');
        } else {
            el.classList.remove('list-filtered-out');
        }
    }

    var sections = list.querySelectorAll('.collection-section');
    if (sections.length > 0) {
        for (var s = 0; s < sections.length; s++) {
            var section = sections[s];
            var nameText = section.getAttribute('data-search') || '';
            var nameHit = (typeof listRowMatchesQuery === 'function')
                ? listRowMatchesQuery(nameText, filter)
                : (!String(filter).trim() || String(nameText).toUpperCase().indexOf(String(filter).trim().toUpperCase()) > -1);
            var rows = section.querySelectorAll('.list-row');
            var anyPieceHit = false;
            for (var r = 0; r < rows.length; r++) {
                var row = rows[r];
                var pieceHit = rowMatches(row);
                if (pieceHit) {
                    anyPieceHit = true;
                }
                // Name match (or empty query): keep all pieces visible.
                setFilteredOut(row, !nameHit && !pieceHit);
            }
            setFilteredOut(section, !nameHit && !anyPieceHit);
        }
        return;
    }

    var flatRows = list.querySelectorAll('.list-row');
    for (var i = 0; i < flatRows.length; i++) {
        setFilteredOut(flatRows[i], !rowMatches(flatRows[i]));
    }
}
