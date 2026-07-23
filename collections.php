<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='collections';
$_SESSION['adminpage']=false;
include "common/header.php";

adminListPageBegin('Archiv', 'Mappen');
?>
<div id="Liste">
<?php
$sql = sprintf('SELECT `Index` FROM `%sCollection` ORDER BY `Name`;',
$GLOBALS['dbprefix']
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
while($row = mysqli_fetch_array($dbr)) {
    $M = new Collections;
    $M->load_by_id($row['Index']);
    echo $M->printContent();
}
?>
</div>
<?php
adminListPageEnd();
include "common/footer.php";
?>
