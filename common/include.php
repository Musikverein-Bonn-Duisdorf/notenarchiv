<?php

include "config.php";
include "config/ConfigDefaults.php";
include "config/SchemaVersion.php";
include "libs/helpers.php";
include "libs/uiShell.php";
include "libs/colorschemes.php";
$optionsDB = loadconfig();
global $optionsDB;
include "version.php";
include "libs/git.php";
include "libs/user.php";
include "libs/log.php";
include "libs/listChunk.php";
include "libs/div.php";
include "libs/Composition.php";
include "libs/Part.php";
include "libs/Collections.php";
include "libs/Collection.php";
include "libs/Composer.php";
include "libs/Publisher.php";
include "libs/entityAvatar.php";
include "libs/ScoreFile.php";
include "libs/RehearsalPhase.php";
include "libs/Stimmsatz.php";
include "libs/NextcloudClient.php";
include "libs/SQLtable.php";
include "libs/SchemaManager.php";
include "libs/backup.php";
include "libs/ssoTicket.php";
include "libs/identityPermissions.php";
include "libs/archivPermissions.php";
include "libs/appToken.php";
include "libs/apiHelpers.php";
?>