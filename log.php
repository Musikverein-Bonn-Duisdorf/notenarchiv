<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
$_SESSION['page']='log';
$_SESSION['adminpage']=true;
include "common/header.php";
requirePermission('perm_showLog');
adminListPageBegin('System', 'Log');
?>
<div id="header" class="w3-hide"></div>
<?php
$sql = sprintf('SELECT `Index` FROM `%sLog` ORDER BY `Index` DESC LIMIT 1000;',
$GLOBALS['dbprefix']
);
$dbr = mysqli_query($conn, $sql);
sqlerror();
while($row = mysqli_fetch_array($dbr)) {
    $M = new Log;
    $M->load_by_id($row['Index']);
    echo $M->printTableLine();
}
?>
<script>
Element.prototype.appendAfter = function (element) {
    element.parentNode.insertBefore(this, element.nextSibling);
}, false;

    function getLog() {
	if (window.XMLHttpRequest) {
	    xmlhttp=new XMLHttpRequest();
	}
	else {
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange=function() {
	    if (xmlhttp.readyState==4 && xmlhttp.status==200 && xmlhttp.responseText) {
            var parent = document.getElementById("header");
            var NewElement = document.createElement("div");
            NewElement.appendAfter(parent.nextSibling);
            let doc = new DOMParser().parseFromString(xmlhttp.responseText, 'text/html');
            let div = doc.body.firstChild;
            NewElement.parentNode.replaceChild(div, NewElement);
	    }
	}
    var parent = document.getElementById("header");
    var first = parent.nextSibling.nextSibling;
    var maxIndex = parseInt(first.id);
    if(maxIndex > 0) {
        var str = "getLog.php?id="+<?php echo "\"".$GLOBALS['cronID']."\""; ?>+"&maxIndex="+maxIndex;
        xmlhttp.open("GET", str, true);
        xmlhttp.send();
    }
    }
var interval = setInterval(getLog, 5000);

</script>
<?php
adminListPageEnd();
include "common/footer.php";
?>
