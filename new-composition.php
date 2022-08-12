<?php
session_start();
$_SESSION['page']='newcomposition';
include "common/header.php";
$fill = false;
if(isset($_POST['Index'])) {
    $n = new Composition;
    $n->load_by_id($_POST['Index']);
    if($n->Index > 0) {
        $fill = true;
    }
}
$edit = 1;
if(isset($_POST['mode'])) {
    if($_POST['mode'] == "compositionedit") {
        $edit = 2;
    }
}
if($_SESSION['admin']) {
    $edit = 3;
}

$disabled = '';
if($edit!=3) {
    $disabled = 'disabled';
}
else {
    $disabled = '';
}
?>
<div class="w3-container w3-margin-bottom <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
         <h2>St&uuml;ck bearbeiten</h2>
</div>
<div class="w3-panel w3-mobile w3-center w3-col s3 l4">
</div>
<div class="w3-card <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-mobile w3-center w3-border w3-padding w3-col s6 l4">
  <form action="" method="POST">
    <label>Titel</label>
    <input class="w3-input w3-border <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-margin-bottom w3-mobile" name="Title" type="text" placeholder="Titel" <?php if($fill) echo "value=\"".$n->Title."\" ".$disabled; ?>>
    <label>Archivnummer</label>
<input class="w3-input w3-border <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-margin-bottom w3-mobile" name="RegistrationNumber" type="number" <?php if($fill) {echo "value=\"".$n->RegistrationNumber."\" ".$disabled;} else { echo "value=\"".nextArchiverNumber()."\" ".$disabled;} ?>>
    <label>Komponist</label>
<select class="w3-input w3-border <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-margin-bottom w3-mobile" name="Composer" <?php echo $disabled; ?>>
      <?php
  if($fill) {
      echo ComposersOption($n->Composer);
  }
  else {
      echo ComposersOption(0);
  }
?>
    </select>
    <label>Arrangeur</label>
<select class="w3-input w3-border <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-margin-bottom w3-mobile" name="Arranger" <?php echo $disabled; ?>>
      <?php
  if($fill) {
      echo ComposersOption($n->Arranger);
  }
  else {
      echo ComposersOption(0);
  }
?>
    </select>
    <label>Verlag</label>
<select class="w3-input w3-border <?php echo $GLOBALS['optionsDB']['colorInputBackground']; ?> w3-margin-bottom w3-mobile" name="Publisher" <?php echo $disabled; ?>>
      <?php
  if($fill) {
      echo PublishersOption($n->Publisher);
  }
  else {
      echo PublishersOption(0);
  }
?>
    </select>

    <input type="hidden" name="Index" <?php if($fill) echo "value=\"".$n->Index."\""; ?>>
    <input class="w3-btn w3-col l6 m6 s12 <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-margin-bottom w3-mobile" type="submit" name="insert" value="speichern">
    <?php
      if($fill && $edit != 2) {
      ?>
    <input class="w3-btn w3-col l6 m6 s12 <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-margin-bottom w3-mobile" type="submit" name="passwd" value="Zufallspasswort generieren">
    <input class="w3-btn w3-col l6 m6 s12 <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-margin-bottom w3-mobile" type="submit" name="newmail" value="Email mit Link senden">
    <?php
}
      ?>
  </form>
<?php if($fill) { ?>
<button class="w3-btn w3-col l6 m6 s12 <?php echo $GLOBALS['optionsDB']['colorBtnDelete']; ?> w3-border w3-margin-bottom w3-mobile" onclick="document.getElementById('delmodal').style.display='block'">l&ouml;schen</button>
<?php } ?>
<?php if($fill) { ?>
         <div class="w3-row"><a href="<?php echo $n->getLink(); ?>"><?php echo $n->getLink(); ?></a></div>
<?php } ?>
</div>
<div class="w3-panel w3-mobile w3-center w3-col s3 l4">
</div>
<?php if($fill) { ?>
    <div id="delmodal" class="w3-modal">
    <div class="w3-modal-content w3-card">
    <header class="w3-container w3-row <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
    <span onclick="document.getElementById('delmodal').style.display='none'" class="w3-button w3-display-topright">&times;</span>
    <h2>L&ouml;schen best&auml;tigen</h2>
    </header>
    <div class="w3-container w3-row w3-center w3-padding w3-margin w3-card <?php echo $GLOBALS['optionsDB']['colorWarning']; ?>">Sind Sie sicher, dass sie <b><?php echo $n->Vorname." ".$n->Nachname; ?></b> l&ouml;schen wollen?</div>
    <div class="w3-container w3-mobile">
    <form action="musiker.php" method="POST">
    <input type="hidden" name="Index" <?php if($fill) echo "value=\"".$n->Index."\""; ?>>
    <div class="w3-row">
    <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
    <button class="w3-btn w3-col l4 m4 s8 w3-center <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-margin-bottom w3-mobile" type="submit" name="delete" value="delete">ja</button>
    <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
    </div>
    </form>
    <div class="w3-row">
    <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
    <button class="w3-btn w3-col l4 m4 s8 w3-center <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-margin-bottom w3-mobile" onclick="document.getElementById('delmodal').style.display='none'">nein</button>
    <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
    </div>
    </div>
    </div>
    </div>
<?php } ?>>
    <div class="w3-row">&nbsp;</div>
<?php
include "common/footer.php";
?>
