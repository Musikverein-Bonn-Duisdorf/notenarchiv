<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='update';
$_SESSION['adminpage']=true;
include "common/header.php";
requireAdmin();

require_once __DIR__.'/libs/SQLtable.php';
require_once __DIR__.'/config/ConfigDefaults.php';
require_once __DIR__.'/libs/SchemaManager.php';

adminListPageBegin('System', 'Update');
?>
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
    $sql = sprintf("SELECT * FROM `%sconfig` WHERE `Parameter` = '%s';",
		   $GLOBALS['dbprefix'],
		   $para[$i]
    );
    $dbr = mysqli_query($GLOBALS['conn'], $sql);
    sqlerror();
    $row = mysqli_fetch_array($dbr);
    if(!$row || $row['Parameter'] != $para[$i]) {
        $sql = sprintf("INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%s', '%s', '%s');",
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

$schemaMgr = new SchemaManager();
$schemaMgr->repair();
echo "<div class=\"w3-row w3-container w3-border w3-border-black w3-padding ".$GLOBALS['optionsDB']['colorLogInfo']."\"><div class=\"w3-col l2 m2 s2\"><b>Schema</b></div><div class=\"w3-col l10 m10 s10\"><pre>".htmlspecialchars($schemaMgr->formatReportText())."</pre></div></div>";

adminListPageEnd();
include "common/footer.php";
?>
