<?php
session_start();
$_SESSION['page']='collections';
$_SESSION['adminpage']=false;
include "common/header.php";

?>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar'] ;?>">
    <h2>Mappen</h2>
</div>
<div id="Liste">
<?php
$now = date("Y-m-d");
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
include "common/footer.php";
?>
