<?php

include "config.php";
include "libs/helpers.php";
$optionsDB = loadconfig();
global $optionsDB;
include "version.php";
include "libs/user.php";
include "libs/log.php";
include "libs/div.php";
include "libs/Composition.php";
include "libs/Part.php";
include "libs/Collections.php";
include "libs/Collection.php";
include "libs/Composer.php";
include "libs/ScoreFile.php";
include "libs/RehearsalPhase.php";
include "libs/Stimmsatz.php";
include "libs/NextcloudClient.php";
include "libs/SQLtable.php";
include "libs/SchemaManager.php";
include "libs/ssoTicket.php";
?>