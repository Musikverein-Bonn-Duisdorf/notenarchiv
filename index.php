<?php
session_start();
$_SESSION['page']='home';
$_SESSION['adminpage']=false;
include "common/header.php";
if(isset($_POST['proxy'])) {
    $user = $_POST['proxy'];
    $proxy = new User;
    $proxy->load_by_id($user);
}
else {
    $user = $_SESSION['userid'];
}
?>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar'] ;?>">
    <h2>Stückliste</h2>
</div>
<?php
$now = date("Y-m-d");
if($GLOBALS['optionsDB']['entriesMainPage'] > 0) {
    $sql = sprintf('SELECT `Index` FROM `%sCompositions` ORDER BY `RegistrationNumber` DESC LIMIT %s;',
		   $GLOBALS['dbprefix'],
		   $now,
		   $GLOBALS['optionsDB']['entriesMainPage']
    );
}
else {
    $sql = sprintf('SELECT `Index` FROM `%sCompositions` ORDER BY `RegistrationNumber` DESC;',
		   $GLOBALS['dbprefix'],
		   $now,
    );
}
$dbr = mysqli_query($conn, $sql);
sqlerror();
while($row = mysqli_fetch_array($dbr)) {
    $M = new Composition;
    $M->load_by_id($row['Index']);
    echo $M->printLine();
}
?>
<?php
include "common/footer.php";
?>
