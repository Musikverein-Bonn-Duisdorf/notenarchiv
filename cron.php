<?php
/**
 * Cron / remote backup entrypoint (ARCHIV-2 / ARCHIV-50).
 *
 * CLI:  php cron.php <cronId> backup > backup.zip
 *       php cron.php <cronId> backup-files > files.zip
 * HTTP: curl -fsS "https://HOST/cron.php?id=BACKUPTOKEN&cmd=backup" -o backup.zip
 *       curl -fsS "https://HOST/cron.php?id=BACKUPTOKEN&cmd=backup-files" -o files.zip
 *       (HTTP uses $backupToken only, never $cronID)
 */
if(PHP_SAPI === 'cli') {
    global $argv;
    if(!isset($argv[1], $argv[2])) {
        fwrite(STDERR, "Usage: php cron.php <cronId> <cmd>\n");
        exit(1);
    }
    $_GET['id'] = (string)$argv[1];
    $_GET['cmd'] = (string)$argv[2];
}

require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include 'common/include.php';

if(!isset($_GET['cmd'])) {
    die("no command specified\n");
}

$cmd = (string)$_GET['cmd'];

if($cmd === 'backup' || $cmd === 'backup-files') {
    if(PHP_SAPI === 'cli') {
        if(!isset($_GET['id']) || (string)$_GET['id'] !== (string)$GLOBALS['cronID']) {
            die("ID invalid\n");
        }
    }
    else {
        $provided = backupHttpExtractProvidedToken();
        if(!backupHttpTokenValid($provided)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            if(!backupHttpTokenConfigured()) {
                die("HTTP backup disabled: set \$backupToken in config.php (min ".BACKUP_HTTP_TOKEN_MIN_LEN." chars), or use backup.php / CLI: php cron.php <cronId> backup\n");
            }
            die("HTTP backup unauthorized: invalid backup token\n");
        }
    }
    mkAdmin();
    try {
        if($cmd === 'backup-files') {
            sendFilesBackupDownload();
        }
        else {
            sendBackupDownload();
        }
    }
    catch(Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Backup failed: ".$e->getMessage()."\n";
        exit(1);
    }
}

die("unknown command\n");
?>
