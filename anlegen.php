<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page'] = 'anlegen';
$_SESSION['adminpage'] = true;

include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

if(empty($_SESSION['admin'])) {
    include 'common/header.php';
    echo '<meta http-equiv="refresh" content="0; URL=index.php" />';
    include 'common/footer.php';
    exit;
}

if(isset($_POST['insertComposer'])) {
    $n = new Composer;
    $n->fill_from_array($_POST);
    $n->save();
    header('Location: composers.php');
    exit;
}
if(isset($_POST['insertPublisher'])) {
    $n = new Publisher;
    $n->fill_from_array($_POST);
    $n->save();
    header('Location: publishers.php');
    exit;
}
if(isset($_POST['savePiece'])) {
    $piece = new Composition;
    if(!empty($_POST['Index']) && (int)$_POST['Index'] > 0) {
        $piece->load_by_id((int)$_POST['Index']);
    }
    $piece->fill_from_array($_POST);
    $piece->save();
    header('Location: composition.php?id='.(int)$piece->Index);
    exit;
}

include 'common/header.php';

$inputBg = isset($GLOBALS['optionsDB']['colorInputBackground'])
    ? (string)$GLOBALS['optionsDB']['colorInputBackground']
    : '';
$btnSubmit = isset($GLOBALS['optionsDB']['colorBtnSubmit'])
    ? (string)$GLOBALS['optionsDB']['colorBtnSubmit']
    : '';

$piece = new Composition;
$piece->RegistrationNumber = nextArchiverNumber();
$backList = '<a class="w3-button w3-border '.htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8').'" href="index.php">Zur Liste</a>';
?>
<div class="w3-container w3-margin-bottom termin-page">
<div class="profile-shell termin-shell">
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Archiv</p>
      <h2 class="profile-title">Anlegen</h2>
    </div>
    <div class="profile-hero-actions">
      <div class="profile-actions">
        <div class="profile-actions-primary">
          <?php echo $backList; ?>
        </div>
      </div>
    </div>
  </header>

  <div class="termin-grid">
    <section class="profile-col" aria-labelledby="anlegen-stueck">
      <form class="profile-form" action="anlegen.php" method="POST">
        <h3 id="anlegen-stueck" class="profile-col-title">Stück</h3>
        <input name="Index" type="hidden" value="0">
        <div class="profile-field">
          <label class="profile-label" for="pieceTitle">Titel</label>
          <input id="pieceTitle" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="Title" type="text" required>
        </div>
        <div class="profile-field">
          <label class="profile-label" for="pieceReg">Inventarnummer</label>
          <input id="pieceReg" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="RegistrationNumber" type="number" value="<?php echo (int)$piece->RegistrationNumber; ?>">
        </div>
        <div class="profile-field">
          <label class="profile-label" for="pieceComposer">Komponist</label>
          <select id="pieceComposer" name="Composer" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo ComposersOption(0); ?>
          </select>
        </div>
        <div class="profile-field">
          <label class="profile-label" for="pieceArranger">Arrangeur</label>
          <select id="pieceArranger" name="Arranger" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo ComposersOption(0); ?>
          </select>
        </div>
        <div class="profile-field">
          <label class="profile-label" for="pieceYear">Jahr</label>
          <input id="pieceYear" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="Year" type="number">
        </div>
        <div class="profile-field">
          <label class="profile-label" for="piecePublisher">Verlag</label>
          <select id="piecePublisher" name="Publisher" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo PublishersOption(0); ?>
          </select>
        </div>
        <div class="profile-field">
          <label class="profile-label" for="pieceGrade">Schwierigkeit</label>
          <input id="pieceGrade" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="Grade" type="number" step="0.5" min="0" max="6">
        </div>
        <div class="profile-field">
          <label class="profile-label" for="pieceTime">Spielzeit</label>
          <input id="pieceTime" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" name="PerformanceTime" type="text" placeholder="00:00">
        </div>
        <div class="profile-field">
          <button class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border" type="submit" name="savePiece" value="1">Speichern</button>
        </div>
      </form>
    </section>

    <section class="profile-col" aria-labelledby="anlegen-komponist">
      <form class="profile-form" action="anlegen.php" method="POST">
        <h3 id="anlegen-komponist" class="profile-col-title">Komponist</h3>
        <div class="profile-field">
          <label class="profile-label" for="composerFirst">Vorname</label>
          <input id="composerFirst" name="FirstName" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="profile-field">
          <label class="profile-label" for="composerLast">Nachname</label>
          <input id="composerLast" name="LastName" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="profile-field">
          <button class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border" type="submit" name="insertComposer" value="1">Speichern</button>
        </div>
      </form>
    </section>

    <section class="profile-col" aria-labelledby="anlegen-verlag">
      <form class="profile-form" action="anlegen.php" method="POST">
        <h3 id="anlegen-verlag" class="profile-col-title">Verlag</h3>
        <div class="profile-field">
          <label class="profile-label" for="publisherName">Name</label>
          <input id="publisherName" name="Name" type="text" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="profile-field">
          <label class="profile-label" for="publisherAddress">Adresse</label>
          <textarea id="publisherAddress" name="Address" class="w3-input w3-border profile-control <?php echo htmlspecialchars($inputBg, ENT_QUOTES, 'UTF-8'); ?>" rows="3"></textarea>
        </div>
        <div class="profile-field">
          <button class="w3-btn profile-btn-primary <?php echo htmlspecialchars($btnSubmit, ENT_QUOTES, 'UTF-8'); ?> w3-border" type="submit" name="insertPublisher" value="1">Speichern</button>
        </div>
      </form>
    </section>
  </div>
</div>
</div>
<?php
include 'common/footer.php';
?>
