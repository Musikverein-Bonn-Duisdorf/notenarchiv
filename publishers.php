<?php
session_start();
$_SESSION['page']='publishers';
$_SESSION['adminpage']=false;
include "common/header.php";

if(isset($_POST['insert'])) {
    $n = new Publisher;
    $n->fill_from_array($_POST);
    $n->save();
}
if(isset($_POST['update'])) {
    $n = new Publisher;
    $n->load_by_id($_POST['Index']);
    $n->fill_from_array($_POST);
    $n->save();
}
if(isset($_POST['delete'])) {
    $n = new Publisher;
    $n->load_by_id($_POST['Index']);
    $n->delete();
}
if($_SESSION['admin']) {
    $sql = sprintf('SELECT COUNT(`Index`) AS `Count` FROM `%sPublishers`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    $row = mysqli_fetch_array($dbr);
    $nPublishers = $row['Count'];
?>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
    <h2>Verl&auml;ge (<?php echo $nPublishers; ?>)</h2>
</div>

<div class="w3-row">
  <input class="w3-input w3-border w3-padding w3-col l6 s6 m6" type="text" placeholder="Suchen..." id="filterString" onkeyup="filterPieces()">
  <div onclick="document.getElementById('inputModal').style.display='block'" class="w3-col l1 m6 s6 w3-center w3-padding <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?>"><i class="fas fa-plus"></i></div>
</div>
<div id="inputModal" class="w3-modal">
  <form class="w3-modal-content" action="" method="POST">
    <header class="w3-container w3-row <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
      <span onclick="document.getElementById('inputModal').style.display='none'" class="w3-button w3-display-topright">&times;</span>
      <h2>Verlag anlegen</h2>
    </header>
    <div class="w3-row w3-padding">
      <div class="w3-col l4 m6 s6"><b>Name</b></div>
      <input name="Name" type="text" class="w3-input w3-col l4 m6 s6 <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?>"/>
    </div>
    <div class="w3-row w3-padding">
      <div class="w3-col l4 m6 s6"><b>Address</b></div>
      <input name="Address" type="text" class="w3-input w3-col l4 m6 s6 <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?>"/>
    </div>
    <div class="w3-row w3-padding">
      <input type="submit" name="insert" value="speichern" class="w3-input w3-button w3-col l8 m12 s12 <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?>"/>
    </div>
  </form>
</div>
<div id="Liste">
<?php
    $sql = sprintf('SELECT `Index` FROM `%sPublishers` ORDER BY `Name`;',
    $GLOBALS['dbprefix']
    );
    $dbr = mysqli_query($conn, $sql);
    sqlerror();
    while($row = mysqli_fetch_array($dbr)) {
        $M = new Publisher;
        $M->load_by_id($row['Index']);
        echo $M->printLine();
    }
?>
</div>
<script src="js/filterInstruments.js?<?php echo $GLOBALS['version']['Hash']; ?>"></script>

<?php }
    else {
 ?>
<meta http-equiv="refresh" content="0; URL=index.php" />
<?php
    }

 include "common/footer.php";
?>
