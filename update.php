<?php
session_start();
$_SESSION['page']='update';
$_SESSION['adminpage']=true;
include "common/header.php";
requireAdmin();
?>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar'] ;?>">
    <h2>Updater</h2>
</div>
<div class="w3-container <?php echo $GLOBALS['optionsDB']['colorTitleBar'] ;?>"></div>
<?php
$para=array(
    'defaultCompositionCover'
);
$desc=array(
    'default Cover Image',
);
$value=array(
    "data/default/cover.png",
);
$type=array(
    "string",
);

$N = count($para);
for($i=0; $i<$N; $i++) {
    $sql = sprintf("SELECT * FROM `%sConfig` WHERE `Parameter` = '%s';",
		   $GLOBALS['dbprefix'],
		   $para[$i]
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $row = mysqli_fetch_array($dbr);
    if($row['Parameter'] != $para[$i]) {
        $sql = sprintf("INSERT INTO `%sConfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%s', '%s', '%s');",
                       $GLOBALS['dbprefix'],
                       $para[$i],
                       $value[$i],
                       $type[$i],
                       $desc[$i]
        );
        echo "<div class=\"w3-row w3-container w3-border w3-border-black w3-padding ".$GLOBALS['optionsDB']['colorLogDBInsert']."\"><div class=\"w3-col l2 m2 s2\"><b>INSERT</b></div><div class=\"w3-col l10 m10 s10\">".htmlspecialchars($sql)."</div></div>";
        mysqli_query($GLOBALS['conn'], $sql);
    }
    else {
        echo "<div class=\"w3-row w3-container w3-border w3-border-black w3-padding ".$GLOBALS['optionsDB']['colorLogInfo']."\"><div class=\"w3-col l2 m2 s2\">Skip</div><div class=\"w3-col l10 m10 s10\">".$para[$i]."</div></div>";
    }
}

?>

<?php
include "common/footer.php";
?>
