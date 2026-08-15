<?php
/** PUT /api/collections/items.php — JSON: id, items: [{id, number}, ...] */
require_once dirname(__DIR__).'/_bootstrap.php';
apiRequireMethod('PUT');
apiRequireBearerAdmin();

$body = archivApiReadJsonBody();
$id = isset($body['id']) ? (int)$body['id'] : 0;
$items = isset($body['items']) && is_array($body['items']) ? $body['items'] : null;
if($id < 1 || $items === null) {
    apiJsonExit(array('error' => 'invalid_payload'), 400);
}
$col = new Collections;
$col->load_by_id($id);
if((int)$col->Index < 1) {
    apiJsonExit(array('error' => 'not_found'), 404);
}
$normalized = array();
foreach($items as $row) {
    if(!is_array($row)) {
        continue;
    }
    $compId = isset($row['id']) ? (int)$row['id'] : 0;
    if($compId < 1) {
        continue;
    }
    $normalized[] = array(
        'id' => $compId,
        'number' => isset($row['number']) ? (int)$row['number'] : 0,
    );
}
archivSyncCollectionItemsForCollection($id, $normalized);
apiJsonExit(array('ok' => true, 'items' => $col->getItemsChipSpec()));
?>
