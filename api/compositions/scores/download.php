<?php
/** GET /api/compositions/scores/download.php?id= — binary download */
require_once dirname(__DIR__, 2).'/_bootstrap.php';
apiRequireMethod('GET');
apiRequireBearerAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id < 1) {
    apiJsonExit(array('error' => 'invalid_id'), 400);
}
$sf = new ScoreFile;
$sf->load_by_id($id);
if((int)$sf->Index < 1 || !$sf->FilePath) {
    apiJsonExit(array('error' => 'not_found'), 404);
}
$piece = new Composition;
$piece->load_by_id((int)$sf->Composition);
$path = $piece->getFilePathPHP().$sf->FilePath;
if(!is_file($path)) {
    apiJsonExit(array('error' => 'file_missing'), 404);
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = array(
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
);
$mime = isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.basename($path).'"');
header('Content-Length: '.(string)filesize($path));
header('Cache-Control: no-store');
readfile($path);
exit;
?>
