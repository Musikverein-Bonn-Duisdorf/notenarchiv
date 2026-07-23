<?php
/**
 * Chunk loaders for infinite-scroll lists (Melde-parity, Archiv subset).
 * Returns array: html, nextCursor (string), hasMore (bool).
 */

function listChunkLimit($requested = 50) {
    $n = (int)$requested;
    if($n < 1) $n = 50;
    if($n > 100) $n = 100;
    return $n;
}

function listChunkLog($beforeIndex, $limit) {
    $limit = listChunkLimit($limit);
    $beforeIndex = (int)$beforeIndex;
    if($beforeIndex > 0) {
        $sql = sprintf(
            'SELECT `Index` FROM `%sLog` WHERE `Index` < %d ORDER BY `Index` DESC LIMIT %d;',
            $GLOBALS['dbprefix'],
            $beforeIndex,
            $limit + 1
        );
    }
    else {
        $sql = sprintf(
            'SELECT `Index` FROM `%sLog` ORDER BY `Index` DESC LIMIT %d;',
            $GLOBALS['dbprefix'],
            $limit + 1
        );
    }
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $ids = array();
    if($dbr) {
        while($row = mysqli_fetch_array($dbr)) {
            $ids[] = (int)$row['Index'];
        }
    }
    $hasMore = count($ids) > $limit;
    if($hasMore) {
        $ids = array_slice($ids, 0, $limit);
    }
    $html = '';
    $next = $beforeIndex;
    ob_start();
    foreach($ids as $id) {
        $M = new Log;
        $M->load_by_id($id);
        $M->printTableLine();
        $next = $id;
    }
    $html = ob_get_clean();
    if($html === false) $html = '';
    return array(
        'html' => $html,
        'nextCursor' => (string)$next,
        'hasMore' => $hasMore,
    );
}

function listChunkRenderSentinelAttrs($type, $cursor, $hasMore, $filterFn = '') {
    $attrs = ' id="listSentinel" data-list-type="'.htmlspecialchars($type, ENT_QUOTES, 'UTF-8').'"'
        .' data-cursor="'.htmlspecialchars((string)$cursor, ENT_QUOTES, 'UTF-8').'"'
        .' data-has-more="'.($hasMore ? '1' : '0').'"';
    if($filterFn !== '') {
        $attrs .= ' data-filter-fn="'.htmlspecialchars($filterFn, ENT_QUOTES, 'UTF-8').'"';
    }
    return $attrs;
}

/**
 * Full status/sentinel bar for infinite scroll (loading + end-of-list).
 * @param string $extraHtmlAttrs e.g. ' data-extra="user=1"'
 */
function listChunkRenderSentinel($type, $cursor, $hasMore, $filterFn = '', $extraHtmlAttrs = '') {
    $attrs = listChunkRenderSentinelAttrs($type, $cursor, $hasMore, $filterFn).$extraHtmlAttrs;
    if($hasMore) {
        return '<div'.$attrs.' style="clear:both;height:1px;padding:0;margin:0;"></div>';
    }
    return '<div class="w3-panel w3-padding w3-center w3-margin-top w3-light-grey"'
        .$attrs
        .' style="clear:both;">Keine weiteren Einträge</div>';
}
?>
