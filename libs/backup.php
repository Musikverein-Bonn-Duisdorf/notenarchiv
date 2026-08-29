<?php
/**
 * Database and files backup / restore helpers
 * (ARCHIV-2 / ARCHIV-50; Melde-Parität MELD-90/131/132/210).
 *
 * DB dumps only tables matching $dbprefix (archiv_*). Never touches identity
 * tables under $identityPrefix (meldeliste_*). Score files under data/ are a
 * separate ZIP (not packed into the SQL backup).
 */

/** Minimum length for HTTP remote backup token (opt-in). */
define('BACKUP_HTTP_TOKEN_MIN_LEN', 32);

/**
 * Configured HTTP backup token, or empty if unset.
 */
function backupHttpTokenConfiguredValue() {
    if(!isset($GLOBALS['backupToken'])) {
        return '';
    }
    return trim((string)$GLOBALS['backupToken']);
}

/**
 * Whether a usable HTTP backup token is configured.
 */
function backupHttpTokenConfigured() {
    return strlen(backupHttpTokenConfiguredValue()) >= BACKUP_HTTP_TOKEN_MIN_LEN;
}

/**
 * Token from query id=… or Authorization: Bearer ….
 */
function backupHttpExtractProvidedToken() {
    if(isset($_GET['id']) && (string)$_GET['id'] !== '') {
        return (string)$_GET['id'];
    }
    $header = '';
    if(!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = (string)$_SERVER['HTTP_AUTHORIZATION'];
    }
    elseif(!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if(preg_match('/^Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Validate a provided HTTP backup token (timing-safe). Never accepts $cronID.
 */
function backupHttpTokenValid($provided) {
    $provided = (string)$provided;
    if($provided === '' || !backupHttpTokenConfigured()) {
        return false;
    }
    $expected = backupHttpTokenConfiguredValue();
    if(strlen($provided) !== strlen($expected)) {
        return false;
    }
    return hash_equals($expected, $provided);
}

/**
 * @return array
 */
function buildBackupManifest() {
    $version = isset($GLOBALS['version']) && is_array($GLOBALS['version'])
        ? $GLOBALS['version']
        : array('String' => '', 'Date' => '', 'Hash' => '');

    $installed = null;
    $expected = null;
    if(class_exists('SchemaManager')) {
        try {
            $mgr = new SchemaManager();
            $installed = $mgr->getInstalledSchemaVersion();
            $expected = $mgr->getExpectedSchemaVersion();
        }
        catch(Throwable $e) {
            // leave nulls
        }
    }

    return array(
        'app' => 'notenarchiv',
        'createdAt' => gmdate('c'),
        'dbprefix' => isset($GLOBALS['dbprefix']) ? (string)$GLOBALS['dbprefix'] : '',
        'version' => array(
            'String' => isset($version['String']) ? (string)$version['String'] : '',
            'Date' => isset($version['Date']) ? (string)$version['Date'] : '',
            'Hash' => isset($version['Hash']) ? (string)$version['Hash'] : '',
        ),
        'schemaVersion' => array(
            'installed' => $installed,
            'expected' => $expected,
        ),
    );
}

/**
 * Absolute data/ directory. Tests may override via $GLOBALS['backupDataRoot'].
 */
function backupDataRoot() {
    if(!empty($GLOBALS['backupDataRoot'])) {
        return rtrim((string)$GLOBALS['backupDataRoot'], '/');
    }
    $prefix = '';
    if(isset($GLOBALS['optionsDB']['dataDirectory'])) {
        $prefix = rtrim((string)$GLOBALS['optionsDB']['dataDirectory'], '/');
    }
    if($prefix !== '') {
        return $prefix.'/data';
    }
    return dirname(__DIR__).'/data';
}

/**
 * Real path of data/ (follows a directory symlink). Used to pack and replace
 * the persistent tree instead of renaming the symlink itself.
 */
function backupDataLivePath() {
    $root = backupDataRoot();
    if($root === '') {
        return $root;
    }
    $real = realpath($root);
    if($real !== false && is_dir($real)) {
        return $real;
    }
    return $root;
}

/**
 * PHP/session settings for long-running backup or restore.
 */
function backupPrepareLongRun() {
    @set_time_limit(0);
    ignore_user_abort(true);
    if(function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * @param string $relPath Zip/relative path using forward slashes
 */
function backupIsSkippedDataFile($relPath) {
    $base = basename(str_replace('\\', '/', (string)$relPath));
    return $base === '.DS_Store' || $base === 'Thumbs.db';
}

/**
 * @param string $name
 * @return string
 */
function backupNormalizeZipEntryName($name) {
    $name = str_replace('\\', '/', (string)$name);
    $name = str_replace("\0", '', $name);
    return ltrim($name, '/');
}

/**
 * Whether a ZIP entry may be restored under data/.
 *
 * @param string $name
 * @return bool
 */
function backupIsSafeDataZipPath($name) {
    $name = backupNormalizeZipEntryName($name);
    if($name === '' || $name === 'data' || $name === 'data/') {
        return true;
    }
    if(strpos($name, '..') !== false) {
        return false;
    }
    if(strpos($name, 'data/') !== 0) {
        return false;
    }
    return true;
}

/**
 * Regular files under data/ to pack (relative zip path => absolute path).
 *
 * @return array<int,array{path:string,zip:string}>
 */
function backupListDataFiles() {
    $root = backupDataLivePath();
    $out = array();
    if(!is_dir($root)) {
        return $out;
    }
    $flags = FilesystemIterator::SKIP_DOTS
        | FilesystemIterator::CURRENT_AS_FILEINFO
        | FilesystemIterator::UNIX_PATHS;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, $flags)
    );
    foreach($iter as $file) {
        if(!$file->isFile() || $file->isLink()) {
            continue;
        }
        $full = $file->getPathname();
        $rel = substr($full, strlen($root));
        $rel = str_replace('\\', '/', $rel);
        $zip = 'data'.($rel === '' ? '' : $rel);
        $zip = str_replace('//', '/', $zip);
        if(backupIsSkippedDataFile($zip)) {
            continue;
        }
        if(!backupIsSafeDataZipPath($zip)) {
            continue;
        }
        $out[] = array('path' => $full, 'zip' => $zip);
    }
    return $out;
}

/**
 * Recursively delete a file or directory (does not follow symlinks).
 */
function backupRemovePath($path) {
    $path = (string)$path;
    if($path === '' || $path === '/' || $path === '.') {
        return;
    }
    if(is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if(!is_dir($path)) {
        return;
    }
    $items = @scandir($path);
    if($items === false) {
        return;
    }
    foreach($items as $item) {
        if($item === '.' || $item === '..') {
            continue;
        }
        backupRemovePath($path.'/'.$item);
    }
    @rmdir($path);
}

/**
 * Move a directory, copying if rename fails (cross-filesystem).
 */
function backupMoveDir($src, $dest) {
    if(@rename($src, $dest)) {
        return;
    }
    if(!@mkdir($dest, 0775, true) && !is_dir($dest)) {
        throw new RuntimeException('Could not create directory: '.$dest);
    }
    $items = @scandir($src);
    if($items === false) {
        throw new RuntimeException('Could not read directory: '.$src);
    }
    foreach($items as $item) {
        if($item === '.' || $item === '..') {
            continue;
        }
        $from = $src.'/'.$item;
        $to = $dest.'/'.$item;
        if(is_link($from) || is_file($from)) {
            if(!@copy($from, $to)) {
                throw new RuntimeException('Could not copy file: '.$from);
            }
            continue;
        }
        if(is_dir($from)) {
            backupMoveDir($from, $to);
        }
    }
    backupRemovePath($src);
}

/**
 * Replace the live data directory with $newDataDir (moved into place).
 */
function backupReplaceDataDir($newDataDir) {
    $target = backupDataRoot();
    $live = backupDataLivePath();
    $hadLive = is_dir($live);
    if(!$hadLive) {
        $parent = dirname($target);
        if(!is_dir($parent) && !@mkdir($parent, 0775, true)) {
            throw new RuntimeException('Could not create data parent directory.');
        }
        backupMoveDir($newDataDir, $target);
        return;
    }
    $parent = dirname($live);
    $bak = $parent.'/'.basename($live).'.bak-'.bin2hex(random_bytes(4));
    try {
        backupMoveDir($live, $bak);
    }
    catch(Throwable $e) {
        throw new RuntimeException('Could not move current data aside.');
    }
    try {
        backupMoveDir($newDataDir, $live);
    }
    catch(Throwable $e) {
        if(is_dir($bak)) {
            try {
                backupMoveDir($bak, $live);
            }
            catch(Throwable $ignored) {
            }
        }
        throw new RuntimeException('Could not install restored data.');
    }
    backupRemovePath($bak);
}

/**
 * List tables matching the configured db prefix.
 *
 * @return string[]
 */
function backupListPrefixedTables() {
    $conn = $GLOBALS['conn'];
    $prefix = isset($GLOBALS['dbprefix']) ? (string)$GLOBALS['dbprefix'] : '';
    if($prefix === '') {
        return array();
    }
    $like = mysqli_real_escape_string($conn, $prefix.'%');
    $dbr = mysqli_query($conn, "SHOW TABLES LIKE '".$like."'");
    if(!$dbr) {
        sqlerror();
        return array();
    }
    $tables = array();
    while($row = mysqli_fetch_row($dbr)) {
        if(!empty($row[0])) {
            $tables[] = $row[0];
        }
    }
    sort($tables);
    return $tables;
}

/**
 * Escape a SQL literal for dump INSERT statements.
 *
 * @param mixed $value
 * @return string
 */
function backupSqlLiteral($value) {
    if($value === null) {
        return 'NULL';
    }
    if(is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return "'".mysqli_real_escape_string($GLOBALS['conn'], (string)$value)."'";
}

/**
 * Build a full SQL dump for all prefixed tables.
 *
 * @return string
 */
function exportDatabaseSql() {
    $conn = $GLOBALS['conn'];
    $tables = backupListPrefixedTables();
    $out = array();
    $out[] = '-- Notenarchiv database backup';
    $out[] = '-- Created: '.gmdate('c');
    $out[] = 'SET NAMES utf8mb4;';
    $out[] = 'SET FOREIGN_KEY_CHECKS=0;';
    $out[] = 'SET UNIQUE_CHECKS=0;';
    $out[] = 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";';
    $out[] = '';

    foreach($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $createRes = mysqli_query($conn, 'SHOW CREATE TABLE `'.$safe.'`');
        if(!$createRes) {
            sqlerror();
            continue;
        }
        $createRow = mysqli_fetch_assoc($createRes);
        $createSql = '';
        if(is_array($createRow)) {
            foreach($createRow as $k => $v) {
                if(stripos((string)$k, 'Create') === 0) {
                    $createSql = $v;
                    break;
                }
            }
        }
        if($createSql === '') {
            continue;
        }

        $out[] = '-- Table `'.$table.'`';
        $out[] = 'DROP TABLE IF EXISTS `'.$safe.'`;';
        $out[] = $createSql.';';
        $out[] = '';

        $dataRes = mysqli_query($conn, 'SELECT * FROM `'.$safe.'`');
        if(!$dataRes) {
            sqlerror();
            continue;
        }
        $fields = array();
        $fieldInfo = mysqli_fetch_fields($dataRes);
        if($fieldInfo) {
            foreach($fieldInfo as $f) {
                $fields[] = '`'.str_replace('`', '``', $f->name).'`';
            }
        }
        $batch = array();
        $batchSize = 50;
        while($row = mysqli_fetch_row($dataRes)) {
            $vals = array();
            foreach($row as $col) {
                $vals[] = backupSqlLiteral($col);
            }
            $batch[] = '('.implode(',', $vals).')';
            if(count($batch) >= $batchSize) {
                $out[] = 'INSERT INTO `'.$safe.'` ('.implode(',', $fields).') VALUES';
                $out[] = implode(",\n", $batch).';';
                $out[] = '';
                $batch = array();
            }
        }
        if($batch) {
            $out[] = 'INSERT INTO `'.$safe.'` ('.implode(',', $fields).') VALUES';
            $out[] = implode(",\n", $batch).';';
            $out[] = '';
        }
        mysqli_free_result($dataRes);
    }

    $out[] = 'SET FOREIGN_KEY_CHECKS=1;';
    $out[] = 'SET UNIQUE_CHECKS=1;';
    $out[] = '';
    return implode("\n", $out);
}

/**
 * Create a backup ZIP in a temp file.
 *
 * @return array{path:string,filename:string,manifest:array}
 */
function createBackupZipFile() {
    if(!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is required for backups.');
    }

    $manifest = buildBackupManifest();
    $sql = exportDatabaseSql();
    $stamp = gmdate('Y-m-d-His');
    $filename = 'notenarchiv-backup-'.$stamp.'.zip';
    $path = tempnam(sys_get_temp_dir(), 'archivbackup_');
    if($path === false) {
        throw new RuntimeException('Could not create temporary file for backup.');
    }
    $zipPath = $path.'.zip';
    @unlink($path);

    $zip = new ZipArchive();
    if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not open ZIP for writing.');
    }
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    $zip->addFromString('database.sql', $sql);
    $zip->close();

    return array(
        'path' => $zipPath,
        'filename' => $filename,
        'manifest' => $manifest,
    );
}

/**
 * Create a files-only backup ZIP of data/ (PDFs, covers, avatars).
 *
 * @return array{path:string,filename:string,manifest:array}
 */
function createFilesBackupZipFile() {
    if(!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is required for backups.');
    }

    backupPrepareLongRun();

    $dataFiles = backupListDataFiles();
    $manifest = buildBackupManifest();
    $manifest['kind'] = 'files';
    $manifest['includesData'] = true;
    $manifest['dataFiles'] = count($dataFiles);

    $stamp = gmdate('Y-m-d-His');
    $filename = 'notenarchiv-files-'.$stamp.'.zip';
    $path = tempnam(sys_get_temp_dir(), 'archivfiles_');
    if($path === false) {
        throw new RuntimeException('Could not create temporary file for backup.');
    }
    $zipPath = $path.'.zip';
    @unlink($path);

    $zip = new ZipArchive();
    if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not open ZIP for writing.');
    }
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    $addedSinceOpen = 0;
    foreach($dataFiles as $file) {
        if(!is_readable($file['path'])) {
            $zip->close();
            @unlink($zipPath);
            throw new RuntimeException('Cannot read file for backup: '.$file['zip']);
        }
        if(!$zip->addFile($file['path'], $file['zip'])) {
            $zip->close();
            @unlink($zipPath);
            throw new RuntimeException('Could not add file to backup ZIP: '.$file['zip']);
        }
        $addedSinceOpen++;
        if($addedSinceOpen >= 40) {
            if($zip->close() !== true) {
                @unlink($zipPath);
                throw new RuntimeException('Could not write backup ZIP.');
            }
            if($zip->open($zipPath) !== true) {
                @unlink($zipPath);
                throw new RuntimeException('Could not reopen backup ZIP.');
            }
            $addedSinceOpen = 0;
        }
    }
    if($zip->close() !== true) {
        @unlink($zipPath);
        throw new RuntimeException('Could not write backup ZIP.');
    }

    $verify = new ZipArchive();
    if($verify->open($zipPath) !== true) {
        @unlink($zipPath);
        throw new RuntimeException('Backup ZIP unreadable after write.');
    }
    $packed = backupCollectDataZipEntries($verify);
    $verify->close();
    if(count($packed['files']) !== count($dataFiles)) {
        @unlink($zipPath);
        throw new RuntimeException(
            'Backup ZIP missing data files (packed '.count($packed['files']).' of '.count($dataFiles).').'
        );
    }

    return array(
        'path' => $zipPath,
        'filename' => $filename,
        'manifest' => $manifest,
    );
}

/**
 * How the backup was requested (for log messages).
 *
 * @return string cli|http|ui
 */
function backupDownloadVia() {
    if(PHP_SAPI === 'cli') {
        return 'cli';
    }
    $script = isset($_SERVER['SCRIPT_NAME']) ? basename((string)$_SERVER['SCRIPT_NAME']) : '';
    if($script === 'cron.php') {
        return 'http';
    }
    return 'ui';
}

/**
 * Format a byte size for log display (B / KB / MB / GB).
 *
 * @param int $bytes
 * @return string
 */
function backupFormatBytes($bytes) {
    $bytes = max(0, (int)$bytes);
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $value = (float)$bytes;
    $unit = 0;
    while($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    if($unit === 0) {
        return ((int)$value).' B';
    }
    $rounded = round($value, 1);
    if(abs($rounded - round($rounded)) < 0.05) {
        return ((int)round($rounded)).' '.$units[$unit];
    }
    return number_format($rounded, 1, '.', '').' '.$units[$unit];
}

/**
 * Confirm a backup ZIP is readable and non-empty, then write an info Log entry.
 * On failure writes an error Log entry and throws.
 *
 * @param array{path:string,filename:string,manifest?:array} $backup
 * @param string|null $via Override via label (cli|http|ui); null = detect
 * @return int Byte size of the ZIP
 */
function confirmAndLogBackupSuccess($backup, $via = null) {
    $path = isset($backup['path']) ? (string)$backup['path'] : '';
    $filename = isset($backup['filename']) ? (string)$backup['filename'] : '';
    $viaLabel = $via !== null ? (string)$via : backupDownloadVia();
    $size = ($path !== '' && is_file($path)) ? filesize($path) : false;

    if($path === '' || $filename === '' || $size === false || $size <= 0) {
        $detail = ($path === '' || !is_file($path)) ? 'ZIP missing' : 'ZIP empty';
        $logentry = new Log;
        $logentry->error('<b>Database backup failed</b>: '.$detail.' (via '.$viaLabel.')');
        throw new RuntimeException('Backup ZIP missing or empty.');
    }

    $logentry = new Log;
    $logentry->info('<b>Database backup</b> '.htmlspecialchars($filename, ENT_QUOTES, 'UTF-8')
        .' ('.backupFormatBytes((int)$size).', via '.$viaLabel.')');
    return (int)$size;
}

/**
 * Log a failed backup attempt.
 *
 * @param string $message
 * @param string|null $via
 */
function logBackupFailure($message, $via = null) {
    $viaLabel = $via !== null ? (string)$via : backupDownloadVia();
    $safe = htmlspecialchars(trim((string)$message), ENT_QUOTES, 'UTF-8');
    if($safe === '') {
        $safe = 'unknown error';
    }
    $logentry = new Log;
    $logentry->error('<b>Database backup failed</b>: '.$safe.' (via '.$viaLabel.')');
}

/**
 * Confirm a files backup ZIP is readable and non-empty, then write an info Log entry.
 *
 * @param array{path:string,filename:string,manifest?:array} $backup
 * @param string|null $via
 * @return int
 */
function confirmAndLogFilesBackupSuccess($backup, $via = null) {
    $path = isset($backup['path']) ? (string)$backup['path'] : '';
    $filename = isset($backup['filename']) ? (string)$backup['filename'] : '';
    $viaLabel = $via !== null ? (string)$via : backupDownloadVia();
    $size = ($path !== '' && is_file($path)) ? filesize($path) : false;

    if($path === '' || $filename === '' || $size === false || $size <= 0) {
        $detail = ($path === '' || !is_file($path)) ? 'ZIP missing' : 'ZIP empty';
        $logentry = new Log;
        $logentry->error('<b>Files backup failed</b>: '.$detail.' (via '.$viaLabel.')');
        throw new RuntimeException('Backup ZIP missing or empty.');
    }

    $filesPart = '';
    if(isset($backup['manifest']['dataFiles'])) {
        $filesPart = ', '.(int)$backup['manifest']['dataFiles'].' files';
    }
    $logentry = new Log;
    $logentry->info('<b>Files backup</b> '.htmlspecialchars($filename, ENT_QUOTES, 'UTF-8')
        .' ('.backupFormatBytes((int)$size).$filesPart.', via '.$viaLabel.')');
    return (int)$size;
}

/**
 * Log a failed files backup attempt.
 *
 * @param string $message
 * @param string|null $via
 */
function logFilesBackupFailure($message, $via = null) {
    $viaLabel = $via !== null ? (string)$via : backupDownloadVia();
    $safe = htmlspecialchars(trim((string)$message), ENT_QUOTES, 'UTF-8');
    if($safe === '') {
        $safe = 'unknown error';
    }
    $logentry = new Log;
    $logentry->error('<b>Files backup failed</b>: '.$safe.' (via '.$viaLabel.')');
}

/**
 * Send backup ZIP to the HTTP client and exit.
 */
function backupSendZipDownload($path, $filename, $size) {
    if(function_exists('header_remove')) {
        @header_remove();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.$size);
    header('Cache-Control: no-store');
    readfile($path);
    @unlink($path);
    exit;
}

/**
 * Send DB backup ZIP to the HTTP client and exit.
 */
function sendBackupDownload() {
    backupPrepareLongRun();
    try {
        $backup = createBackupZipFile();
    }
    catch(Throwable $e) {
        logBackupFailure($e->getMessage());
        throw $e;
    }

    $size = confirmAndLogBackupSuccess($backup);
    backupSendZipDownload($backup['path'], $backup['filename'], $size);
}

/**
 * Send files backup ZIP to the HTTP client and exit.
 */
function sendFilesBackupDownload() {
    backupPrepareLongRun();
    try {
        $backup = createFilesBackupZipFile();
    }
    catch(Throwable $e) {
        logFilesBackupFailure($e->getMessage());
        throw $e;
    }

    $size = confirmAndLogFilesBackupSuccess($backup);
    backupSendZipDownload($backup['path'], $backup['filename'], $size);
}

/**
 * Human-readable error for $_FILES['backup_zip'], or empty if the upload is usable.
 *
 * @param array|null $file
 */
function backupRestoreUploadError($file) {
    if(!is_array($file) || !isset($file['error'])) {
        return 'Keine ZIP-Datei hochgeladen.';
    }
    $err = (int)$file['error'];
    if($err === UPLOAD_ERR_OK) {
        if(empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return 'Keine ZIP-Datei hochgeladen.';
        }
        return '';
    }
    if($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        $lim = trim((string)ini_get('upload_max_filesize'));
        if($lim === '') {
            $lim = '?';
        }
        return 'ZIP größer als upload_max_filesize ('.$lim.').';
    }
    if($err === UPLOAD_ERR_PARTIAL) {
        return 'ZIP-Upload unvollständig.';
    }
    if($err === UPLOAD_ERR_NO_FILE) {
        return 'Keine ZIP-Datei hochgeladen.';
    }
    return 'ZIP-Upload fehlgeschlagen (Code '.$err.').';
}

/**
 * Split SQL dump into executable statements (naive but sufficient for our exporter).
 *
 * @param string $sql
 * @return string[]
 */
function backupSplitSqlStatements($sql) {
    $statements = array();
    $buffer = '';
    $lines = preg_split("/\r\n|\n|\r/", $sql);
    foreach($lines as $line) {
        $trim = ltrim($line);
        if($trim === '' || strpos($trim, '--') === 0) {
            continue;
        }
        $buffer .= $line."\n";
        if(substr(rtrim($line), -1) === ';') {
            $stmt = trim($buffer);
            if($stmt !== '' && $stmt !== ';') {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }
    $tail = trim($buffer);
    if($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

/**
 * Refuse restore of foreign / dual-prefix dumps into the shared MySQL.
 *
 * @param array|null $manifest
 * @param string $sql
 */
function backupAssertRestoreSafe($manifest, $sql) {
    $prefix = isset($GLOBALS['dbprefix']) ? (string)$GLOBALS['dbprefix'] : '';
    if($prefix === '') {
        throw new RuntimeException('Restore refused: empty $dbprefix.');
    }

    if(is_array($manifest)) {
        if(isset($manifest['app']) && (string)$manifest['app'] !== '' && (string)$manifest['app'] !== 'notenarchiv') {
            throw new RuntimeException(
                'Restore refused: backup app is "'.$manifest['app'].'", expected notenarchiv.'
            );
        }
        if(isset($manifest['dbprefix']) && (string)$manifest['dbprefix'] !== ''
            && (string)$manifest['dbprefix'] !== $prefix) {
            throw new RuntimeException(
                'Restore refused: backup dbprefix "'.$manifest['dbprefix'].'" does not match "'.$prefix.'".'
            );
        }
    }

    if(preg_match_all('/(?:DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO)\s+`([^`]+)`/i', $sql, $m)) {
        foreach($m[1] as $table) {
            if(strpos($table, $prefix) !== 0) {
                throw new RuntimeException(
                    'Restore refused: SQL touches table `'.$table.'` outside prefix `'.$prefix.'`.'
                );
            }
        }
    }
}

/**
 * Execute a SQL dump against the current connection.
 *
 * @param string $sql
 * @return array{statements:int,errors:string[]}
 */
function restoreDatabaseSql($sql) {
    $conn = $GLOBALS['conn'];
    $statements = backupSplitSqlStatements($sql);
    $errors = array();
    $ok = 0;

    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    foreach($statements as $stmt) {
        if(!mysqli_query($conn, $stmt)) {
            $errors[] = mysqli_errno($conn).': '.mysqli_error($conn);
            if(count($errors) >= 10) {
                break;
            }
        }
        else {
            $ok++;
        }
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');

    return array(
        'statements' => $ok,
        'errors' => $errors,
    );
}

/**
 * Restore from a backup ZIP path.
 *
 * @param string $zipPath
 * @param bool $runRepair
 * @return array{manifest:?array,statements:int,errors:string[],repaired:bool}
 */
function restoreBackupZip($zipPath, $runRepair = true) {
    if(!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is required for restore.');
    }
    if(!is_readable($zipPath)) {
        throw new RuntimeException('Backup ZIP not readable: '.$zipPath);
    }

    $zip = new ZipArchive();
    if($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open backup ZIP.');
    }
    $sql = $zip->getFromName('database.sql');
    $manifestRaw = $zip->getFromName('manifest.json');
    $zip->close();

    if($sql === false || $sql === '') {
        throw new RuntimeException('ZIP does not contain database.sql');
    }

    $manifest = null;
    if($manifestRaw !== false && $manifestRaw !== '') {
        $decoded = json_decode($manifestRaw, true);
        if(is_array($decoded)) {
            $manifest = $decoded;
        }
    }

    backupAssertRestoreSafe($manifest, $sql);

    $result = restoreDatabaseSql($sql);
    $repaired = false;
    if($runRepair && empty($result['errors']) && class_exists('SchemaManager')) {
        $mgr = new SchemaManager();
        $mgr->repair();
        $repaired = true;
    }

    return array(
        'manifest' => $manifest,
        'statements' => $result['statements'],
        'errors' => $result['errors'],
        'repaired' => $repaired,
    );
}

/**
 * @param ZipArchive $zip
 * @return array{files:string[],hasData:bool}
 */
function backupCollectDataZipEntries($zip) {
    $files = array();
    $hasData = false;
    $n = $zip->numFiles;
    for($i = 0; $i < $n; $i++) {
        $stat = $zip->statIndex($i);
        if(!is_array($stat) || empty($stat['name'])) {
            continue;
        }
        $name = backupNormalizeZipEntryName($stat['name']);
        if($name !== 'data' && $name !== 'data/' && strpos($name, 'data/') !== 0) {
            continue;
        }
        if(!backupIsSafeDataZipPath($name)) {
            throw new RuntimeException('Unsafe path in backup ZIP: '.$name);
        }
        if(substr($name, -1) === '/' || $name === 'data') {
            continue;
        }
        if(backupIsSkippedDataFile($name)) {
            continue;
        }
        $mode = isset($stat['external_attr']) ? (($stat['external_attr'] >> 16) & 0xFFFF) : 0;
        if($mode !== 0 && (($mode & 0170000) === 0120000)) {
            continue;
        }
        $files[] = $stat['name'];
        $hasData = true;
    }
    return array('files' => $files, 'hasData' => $hasData);
}

/**
 * Snapshot-restore data/ from an already-open ZIP (extract to temp, then replace).
 *
 * @param ZipArchive $zip
 * @param string[] $entryNames
 * @return int Number of files restored
 */
function backupRestoreDataFromZip($zip, $entryNames) {
    $tmp = sys_get_temp_dir().'/archiv-restore-'.bin2hex(random_bytes(8));
    if(!@mkdir($tmp, 0700)) {
        throw new RuntimeException('Could not create temporary directory for restore.');
    }
    $extractedRoot = $tmp.'/data';
    if(!@mkdir($extractedRoot, 0775, true)) {
        backupRemovePath($tmp);
        throw new RuntimeException('Could not create temporary data directory.');
    }

    $count = 0;
    try {
        foreach($entryNames as $entryName) {
            $name = backupNormalizeZipEntryName($entryName);
            if(!backupIsSafeDataZipPath($name) || backupIsSkippedDataFile($name)) {
                continue;
            }
            $rel = substr($name, strlen('data/'));
            if($rel === false || $rel === '' || $rel === $name) {
                continue;
            }
            $dest = $extractedRoot.'/'.$rel;
            $dir = dirname($dest);
            if(!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                throw new RuntimeException('Could not create restore path for '.$name);
            }
            $dirReal = realpath($dir);
            $baseReal = realpath($extractedRoot);
            $prefix = $baseReal.DIRECTORY_SEPARATOR;
            if($dirReal === false || $baseReal === false
                || ($dirReal !== $baseReal && strpos($dirReal, $prefix) !== 0)) {
                throw new RuntimeException('Unsafe restore path in backup ZIP: '.$name);
            }
            $stream = $zip->getStream($entryName);
            if($stream === false) {
                throw new RuntimeException('Could not read ZIP entry: '.$name);
            }
            $out = fopen($dest, 'wb');
            if($out === false) {
                fclose($stream);
                throw new RuntimeException('Could not write restored file: '.$name);
            }
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);
            $count++;
        }
        backupReplaceDataDir($extractedRoot);
    }
    catch(Throwable $e) {
        backupRemovePath($tmp);
        throw $e;
    }
    backupRemovePath($tmp);
    return $count;
}

/**
 * Detect whether a ZIP is a DB backup or a files backup.
 *
 * @param string $zipPath
 * @return string database|files
 */
function backupDetectZipKind($zipPath) {
    if(!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is required for restore.');
    }
    if(!is_readable($zipPath)) {
        throw new RuntimeException('Backup ZIP not readable: '.$zipPath);
    }
    $zip = new ZipArchive();
    if($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open backup ZIP.');
    }
    $sql = $zip->getFromName('database.sql');
    $manifestRaw = $zip->getFromName('manifest.json');
    $dataScan = backupCollectDataZipEntries($zip);
    $zip->close();

    $kind = '';
    if($manifestRaw !== false && $manifestRaw !== '') {
        $decoded = json_decode($manifestRaw, true);
        if(is_array($decoded) && isset($decoded['kind'])) {
            $kind = (string)$decoded['kind'];
        }
    }
    if($kind === 'files' || (($sql === false || $sql === '') && !empty($dataScan['files']))) {
        return 'files';
    }
    if($sql !== false && $sql !== '') {
        return 'database';
    }
    throw new RuntimeException('ZIP is neither a database nor a files backup.');
}

/**
 * Restore data/ from a files-only backup ZIP (snapshot). Does not touch the database.
 *
 * @param string $zipPath
 * @return array{manifest:?array,filesRestored:int,errors:string[]}
 */
function restoreFilesBackupZip($zipPath) {
    if(!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is required for restore.');
    }
    if(!is_readable($zipPath)) {
        throw new RuntimeException('Backup ZIP not readable: '.$zipPath);
    }

    backupPrepareLongRun();

    $zip = new ZipArchive();
    if($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open backup ZIP.');
    }
    $sql = $zip->getFromName('database.sql');
    $manifestRaw = $zip->getFromName('manifest.json');
    $dataScan = backupCollectDataZipEntries($zip);

    $manifest = null;
    if($manifestRaw !== false && $manifestRaw !== '') {
        $decoded = json_decode($manifestRaw, true);
        if(is_array($decoded)) {
            $manifest = $decoded;
        }
    }

    if(is_array($manifest)) {
        if(isset($manifest['app']) && (string)$manifest['app'] !== '' && (string)$manifest['app'] !== 'notenarchiv') {
            $zip->close();
            throw new RuntimeException(
                'Restore refused: backup app is "'.$manifest['app'].'", expected notenarchiv.'
            );
        }
        $prefix = isset($GLOBALS['dbprefix']) ? (string)$GLOBALS['dbprefix'] : '';
        if($prefix !== '' && isset($manifest['dbprefix']) && (string)$manifest['dbprefix'] !== ''
            && (string)$manifest['dbprefix'] !== $prefix) {
            $zip->close();
            throw new RuntimeException(
                'Restore refused: backup dbprefix "'.$manifest['dbprefix'].'" does not match "'.$prefix.'".'
            );
        }
    }

    if($sql !== false && $sql !== '') {
        $zip->close();
        throw new RuntimeException('ZIP contains database.sql; use the database restore.');
    }
    $isFilesKind = is_array($manifest) && isset($manifest['kind']) && (string)$manifest['kind'] === 'files';
    if(count($dataScan['files']) === 0 && !$isFilesKind) {
        $zip->close();
        throw new RuntimeException('ZIP does not contain data files.');
    }

    $errors = array();
    $filesRestored = 0;
    try {
        $filesRestored = backupRestoreDataFromZip($zip, $dataScan['files']);
    }
    catch(Throwable $e) {
        $zip->close();
        throw $e;
    }
    $zip->close();

    return array(
        'manifest' => $manifest,
        'filesRestored' => $filesRestored,
        'errors' => $errors,
    );
}
?>
