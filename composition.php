<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='composition';
include "common/header.php";

$piece = new Composition;
if(isset($_POST['delete'])) {
    $piece = new Composition;
    $piece->load_by_id($_POST['Index']);
    $piece->deleteCover();
}
if(isset($_POST['deleteCollection'])) {
    $coll = new Collection;
    $coll->load_by_id($_POST['Index']);
    $coll->delete();
    $piece->load_by_id($_POST['Composition']);
}
if(isset($_POST['insertCollection'])) {
    $coll = new Collection;
    $coll->fill_from_array($_POST);
    $coll->save();
    $piece->load_by_id($_POST['Composition']);
}
if(isset($_POST['updateCollection'])) {
    $coll = new Collection;
    $coll->load_by_id($_POST['Index']);
    $coll->fill_from_array($_POST);
    $coll->save();
    $piece->load_by_id($_POST['Composition']);
}
if(isset($_POST['partupload'])) {
    $piece = new Composition;
    $piece->load_by_id($_POST['Index']);
    $part = new Part;
    $part->load_by_id($_POST['pIndex']);
    $part->upload($_POST, $_FILES);
}
if(isset($_POST['partdelete'])) {
    $piece = new Composition;
    $piece->load_by_id($_POST['Index']);
    $part = new Part;
    $part->load_by_id($_POST['pIndex']);
    $part->deleteFile();
}
if(isset($_POST['cover'])) {
    $piece = new Composition;
    $piece->load_by_id($_POST['Index']);
    $target_dir = $piece->getFilePathPHP();
    $target_file = $piece->getFilePathPHP()."cover".strrchr($_FILES["coverImage"]["name"], '.');
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
    if(isset($_POST["submit"])) {
        $check = getimagesize($_FILES["coverImage"]["tmp_name"]);
        if($check !== false) {
            echo "File is an image - " . $check["mime"] . ".";
            $uploadOk = 1;
        } else {
            echo "File is not an image.";
            $uploadOk = 0;
        }
        if ($_FILES["coverImage"]["size"] > 500000) {
            echo "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" ) {
            echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
    }
    if ($uploadOk == 0) {
        echo "Sorry, your file was not uploaded.";
    // if everything is ok, try to upload file
    } else {
        $piece->deleteCover();
        if (move_uploaded_file($_FILES["coverImage"]["tmp_name"], $target_file)) {
        } else {
            echo "Sorry, there was an error uploading your file.";
        }
    }
}
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

adminListPageBegin('Archiv', $piece->Title.($piece->ComposerName ? ' ('.$piece->ComposerName.')' : ''));
?>
<div class="w3-hide"><?php /* legacy titlebar removed */ ?></div>
<div class="w3-row">
  <div class="w3-col l5">
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
    <div class="w3-padding w3-row">
      <form class="w3-col l2" action="new-composition.php" method="POST">
	<button class="w3-button <?php echo $GLOBALS['optionsDB']['colorBtnEdit']; ?>" type="submit" value="<?php echo $piece->Index ?>" name="Index">bearbeiten</button>
      </form>
      <button class="w3-button w3-col l2 w3-margin-right <?php echo $GLOBALS['optionsDB']['colorBtnEdit']; ?>" onclick="document.getElementById('collmodal').style.display='block'">Mappen</button>
      <button class="w3-button w3-col l2 <?php echo $GLOBALS['optionsDB']['colorBtnDelete']; ?>" onclick="document.getElementById('delmodal').style.display='block'">l&ouml;schen</button>
    </div>
  </div>

  <div id="delmodal" class="w3-modal">
    <div class="w3-modal-content w3-card">
      <header class="w3-container w3-row <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
	<span onclick="document.getElementById('delmodal').style.display='none'" class="w3-button w3-display-topright">&times;</span>
	<h2>L&ouml;schen best&auml;tigen</h2>
      </header>
      <div class="w3-container w3-row w3-center w3-padding w3-margin w3-card <?php echo $GLOBALS['optionsDB']['colorWarning']; ?>">Sind Sie sicher, dass sie <b><?php echo $piece->Title; ?></b> l&ouml;schen wollen?</div>
      <div class="w3-container w3-mobile">
	<div class="w3-row">
	  <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
          <form action="index.php" method="POST">
	    <button class="w3-btn w3-col l4 m4 s8 w3-center <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-margin-bottom w3-mobile" type="submit" name="Delete" value="<?php echo $piece->Index ?>">ja</button>
          </form>
	  <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
	</div>
	<div class="w3-row">
	  <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
	  <button class="w3-btn w3-col l4 m4 s8 w3-center <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?> w3-border w3-margin-bottom w3-mobile" onclick="document.getElementById('delmodal').style.display='none'">nein</button>
	  <div class="w3-col l4 m4 s2 w3-center">&nbsp;</div>
	</div>
      </div>	  
    </div>
  </div>

  <div id="collmodal" class="w3-modal">
    <div class="w3-modal-content w3-card">
      <header class="w3-container w3-row <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
	<span onclick="document.getElementById('collmodal').style.display='none'" class="w3-button w3-display-topright">&times;</span>
	<h2>Mappenverwaltung</h2>
      </header>
      <?php echo $piece->listCollections(); ?>
    </div>
  </div>

  <div class="w3-col l5">
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
  <div class="w3-col l2">
    <img class="w3-row w3-padding" width="100%" src="<?php echo $piece->getCover()."?".uniqid(); ?>">
    <form class="w3-row" action="" method="POST" enctype="multipart/form-data">
      <input class="w3-col l12 w3-padding" type="file" accept=".png,.jpeg,.gif,.jpg" name="coverImage">
      <input type="hidden" name="Index" value="<?php echo $piece->Index; ?>">
      <input class="w3-col l6 w3-padding w3-button <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?>" type="submit" value="upload" name="cover">
      <input class="w3-col l6 w3-padding w3-button <?php echo $GLOBALS['optionsDB']['colorBtnSubmit']; ?>" type="submit" value="delete" name="delete">
    </form>
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
adminListPageEnd();
include "common/footer.php";
?>
