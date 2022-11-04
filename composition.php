<?php
session_start();
$_SESSION['page']='composition';
include "common/header.php";

$piece = new Composition;
if(isset($_POST['pieceID'])) {
    $piece->load_by_id($_POST['pieceID']);
    if(isset($_POST['instrument'])) {
        $part = new Part;
        $part->Instrument = $_POST['instrument'];
        $part->Composition = $_POST['pieceID'];
        $part->Part = $_POST['part'];
        $part->save();
    }
}

?>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
  <h2><?php echo $piece->Title." (".$piece->ComposerName.")"; ?></h2>
</div>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
  <h3>Übersicht</h3>
</div>
<div class="w3-row">
  <div class="w3-col l6">
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Titel:</div>
      <div class="w3-col l9"><b><?php echo $piece->Title; ?></b></div>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Inventarnummer:</div>
      <div class="w3-col l9"><b><?php echo $piece->RegistrationNumber; ?></b></div>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Komponist:</div>
      <div class="w3-col l9"><b><?php echo $piece->ComposerName; ?></b></div>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Arrangeur:</div>
      <div class="w3-col l9"><b><?php echo $piece->ArrangerName; ?></b></div>
    </div>
  </div>
  <div class="w3-col l6">
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Erscheinungsjahr:</div>
      <div class="w3-col l9"><b><?php echo $piece->Year; ?></b></div>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Verlag:</div>
      <div class="w3-col l9"><b><?php echo $piece->PublisherName; ?></b></div>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Schwierigkeitsgrad:</div>
      <div class="w3-col l9"><b><?php echo $piece->Grade; ?></b></div>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Spielzeit:</div>
      <div class="w3-col l9"><b><?php echo $piece->PerformanceTime; ?></b></div>
    </div>
  </div>
</div>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
  <h3>Stimmverteilung</h3>
</div>
<div class="w3-row w3-margin">
  <form action="composition.php" method="POST">
    <select name="instrument" class="w3-input w3-col l2">
      <?php echo instrumentsOption(); ?>
    </select>
    <input type="hidden" name="pieceID" value="<?php echo $piece->Index; ?>" />
    <input class="w3-input w3-col l1" type="number" name="part" value="1" />
    <button class="w3-button w3-round w3-teal w3-col l1" type="submit" ><i class="fas fa-plus"></i></button>
  </form>
</div>
<div>
  <?php echo $piece->listParts(); ?>
</div>
<?php
include "common/footer.php";
?>
