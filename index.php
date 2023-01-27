<?php
session_start();
$_SESSION['page']='home';
$_SESSION['adminpage']=false;
include "common/header.php";

if(isset($_POST['save'])) {
    $piece = new Composition;
    if($_POST['Index'] > 0) {
        $piece->load_by_id($_POST['Index']);
    }
    $piece->fill_from_array($_POST);
    $piece->save();
    $_POST['pieceID']=$piece->Index;
}

if(isset($_POST['proxy'])) {
    $user = $_POST['proxy'];
    $proxy = new User;
    $proxy->load_by_id($user);
}
else {
    $user = $_SESSION['userid'];
}

$sql = sprintf('SELECT COUNT(`Index`) AS `Count` FROM `%sCompositions`;',
$GLOBALS['dbprefix']
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
$row = mysqli_fetch_array($dbr);
$nPieces = $row['Count'];

?>
<script src="js/filterPieces.js?<?php echo $GLOBALS['version']['Hash']; ?>"></script>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar'] ;?>">
    <h2>Stückliste (<?php echo $nPieces; ?>)</h2>
</div>
<div class="w3-row">
         <input class="w3-input w3-border w3-padding w3-col l6 s12 m12" type="text" placeholder="Suche..." id="filterString" onkeyup="filterPieces()">
</div>
<div id="Liste">
<?php
$now = date("Y-m-d");
$sql = sprintf('SELECT `Index` FROM `%sCompositions` ORDER BY `RegistrationNumber` DESC, `Title` ASC;',
$GLOBALS['dbprefix'],
$now,
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
while($row = mysqli_fetch_array($dbr)) {
    $M = new Composition;
    $M->load_by_id($row['Index']);
    echo $M->printLine();
}
?>
</div>
<?php
include "common/footer.php";
?>
