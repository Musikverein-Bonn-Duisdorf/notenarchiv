<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='newcomposition';
include "common/header.php";

$piece = new Composition;
if(isset($_POST['Index'])) {
    $piece->load_by_id($_POST['Index']);
    $RegNr=$piece->RegistrationNumber;
    $Composer=$piece->Composer;
    $Arranger=$piece->Arranger;
    $Publisher=$piece->Publisher;
}
else {
    $piece->RegistrationNumber=nextArchiverNumber();
}
adminListPageBegin('Archiv', 'Anlegen und Bearbeiten');
?>
<form action="index.php" method="POST" class="w3-row">
  <input name="Index" type="hidden" value="<?php echo $piece->Index; ?>">
  <div class="w3-col l6">
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Titel</div>
      <input class="w3-col w3-input l9" name="Title" type="text" value="<?php echo $piece->Title; ?>">
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Inventarnummer</div>
      <input class="w3-col l9 w3-input" name="RegistrationNumber" type="number" value="<?php echo $piece->RegistrationNumber; ?>">
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Komponist</div>
      <select name="Composer" class="w3-col w3-input l9">
	<?php echo ComposersOption($piece->Composer); ?>
      </select>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Arrangeur</div>
      <select name="Arranger" class="w3-col w3-input l9">
	<?php echo ComposersOption($piece->Arranger); ?>
      </select>
    </div>
  </div>
  <div class="w3-col l6">
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Erscheinungsjahr</div>
      <input class="w3-col w3-input l9" name="Year" type="number" value="<?php echo $piece->Year; ?>">
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Verlag</div>
      <select name="Publisher" class="w3-col w3-input l9">
	<?php echo PublishersOption($piece->Publisher); ?>
      </select>
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Schwierigkeitsgrad</div>
      <input class="w3-col w3-input l9" name="Grade" type="number" step="0.5" min="0" max="6" value="<?php echo $piece->Grade; ?>">
    </div>
    <div class="w3-row w3-card w3-padding w3-margin">
      <div class="w3-col l3">Spielzeit</div>
      <input class="w3-col w3-input l9" name="PerformanceTime" type="text" value="<?php echo $piece->PerformanceTime; ?>" placeholder="00:00">
    </div>
  </div>
  <button class="w3-row w3-margin <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-col l3 w3-button" type="submit" name="save" value="save">Speichern</button>
</form>
<?php
adminListPageEnd();
include "common/footer.php";
?>
