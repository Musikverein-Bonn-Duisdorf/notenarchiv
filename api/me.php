<?php
/** GET /api/me.php */
require_once __DIR__.'/_bootstrap.php';
apiRequireMethod('GET');
$token = apiRequireBearerAdmin();
archivLogApiSessionEstablished($token);

apiJsonExit(array(
    'ok' => true,
    'user' => array(
        'id' => (int)$_SESSION['userid'],
        'name' => isset($_SESSION['username']) ? (string)$_SESSION['username'] : '',
        'admin' => !empty($_SESSION['admin']),
    ),
));
?>
