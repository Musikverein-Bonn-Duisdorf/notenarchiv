<?php
session_start();
$_SESSION['page']='composition';
include "common/header.php";

$piece = new Composition;
if(isset($_POST['delete'])) {
    $piece = new Composition;
    $piece->load_by_id($_POST['Index']);
    $piece->deleteCover();
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

?>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar']; ?>">
  <h2><?php
       echo $piece->Title;
if($piece->ComposerName) echo " (".$piece->ComposerName.")";
?></h2>
</div>
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
    <form class="w3-padding" action="new-composition.php" method="POST">
      <button class="w3-button <?php echo $GLOBALS['optionsDB']['colorBtnEdit']; ?>" type="submit" value="<?php echo $piece->Index ?>" name="Index">bearbeiten</button>
    </form>
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
include "common/footer.php";
?>
