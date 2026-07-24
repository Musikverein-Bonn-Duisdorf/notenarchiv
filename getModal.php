<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include 'common/include.php';

header('Content-Type: text/html; charset=utf-8');

if(!isset($_SESSION['userid']) || !(int)$_SESSION['userid']) {
    http_response_code(401);
    echo '<div class="w3-container w3-padding"><p>Nicht angemeldet.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
    exit;
}

$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0 || $type === '') {
    http_response_code(400);
    echo '<div class="w3-container w3-padding"><p>Ungültige Anfrage.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
    exit;
}

$isAdmin = !empty($_SESSION['admin']);

switch($type) {
case 'composition':
    $piece = new Composition;
    $piece->load_by_id($id);
    if(!(int)$piece->Index) {
        http_response_code(404);
        echo '<div class="w3-container w3-padding"><p>Stück nicht gefunden.</p></div>';
        exit;
    }
    echo $piece->getModalHtml($isAdmin);
    break;

case 'composer':
    if(!$isAdmin) {
        http_response_code(403);
        echo '<div class="w3-container w3-padding"><p>Keine Berechtigung.</p></div>';
        exit;
    }
    $c = new Composer;
    $c->load_by_id($id);
    if(!(int)$c->Index) {
        http_response_code(404);
        echo '<div class="w3-container w3-padding"><p>Komponist nicht gefunden.</p></div>';
        exit;
    }
    echo $c->getModalHtml(true);
    break;

case 'publisher':
    if(!$isAdmin) {
        http_response_code(403);
        echo '<div class="w3-container w3-padding"><p>Keine Berechtigung.</p></div>';
        exit;
    }
    $p = new Publisher;
    $p->load_by_id($id);
    if(!(int)$p->Index) {
        http_response_code(404);
        echo '<div class="w3-container w3-padding"><p>Verlag nicht gefunden.</p></div>';
        exit;
    }
    echo $p->getModalHtml(true);
    break;

default:
    http_response_code(400);
    echo '<div class="w3-container w3-padding"><p>Unbekannter Typ.</p><button class="w3-button" onclick="closeModal()">Schließen</button></div>';
    exit;
}
?>
