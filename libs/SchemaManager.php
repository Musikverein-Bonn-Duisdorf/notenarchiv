<?php
/**
 * Schema create / check / repair based on config/DBconfig.json.
 * Slim variant for Notenarchiv (no Melde-specific migrations).
 */
class SchemaManager
{
    private $schema = array();
    private $schemaPath;
    private $report = array();

    public function __construct($schemaPath = null) {
        if($schemaPath === null) {
            $schemaPath = dirname(__DIR__).'/config/DBconfig.json';
        }
        $this->schemaPath = $schemaPath;
        $this->loadSchema();
    }

    private function loadSchema() {
        if(!is_readable($this->schemaPath)) {
            throw new RuntimeException('DBconfig not readable: '.$this->schemaPath);
        }
        $json = file_get_contents($this->schemaPath);
        $data = json_decode($json, true);
        if(!is_array($data)) {
            throw new RuntimeException('Invalid DBconfig JSON');
        }
        $this->schema = $data;
    }

    public function getSchema() {
        return $this->schema;
    }

    public function getReport() {
        return $this->report;
    }

    /**
     * Statuses that matter in check/repair UI (skip noisy "ok").
     * @param string $status
     * @return bool
     */
    public static function isNotableStatus($status) {
        return in_array((string)$status, array(
            'created',
            'fixed',
            'removed',
            'missing',
            'mismatch',
            'error',
            'obsolete',
        ), true);
    }

    public function hasErrors() {
        foreach($this->report as $entry) {
            if($entry['status'] === 'error' || $entry['status'] === 'missing' || $entry['status'] === 'mismatch') {
                return true;
            }
        }
        return false;
    }

    public function hasChanges() {
        foreach($this->report as $entry) {
            if(in_array($entry['status'], array('created', 'fixed', 'missing', 'mismatch', 'removed'), true)) {
                return true;
            }
        }
        return false;
    }

    private function addReport($level, $target, $status, $message = '', $detail = null) {
        $this->report[] = array(
            'level' => $level,
            'target' => $target,
            'status' => $status,
            'message' => $message,
            'detail' => $detail,
        );
    }

    public function create() {
        $this->assertWritableConfigPrefix();
        $this->report = array();
        $this->processSchema(true, false);
        // Legacy vor Defaults: vorhandene WebSite*-Werte nach Archiv* übernehmen
        $this->migrateLegacySharedConfigKeys(true);
        $this->pruneObsoleteSchema(true);
        $this->checkConfigDefaults(true);
        $this->finalizeSchemaVersion();
        return $this->report;
    }

    public function repair() {
        $this->assertWritableConfigPrefix();
        $this->report = array();
        $this->processSchema(true, true);
        $this->migrateLegacySharedConfigKeys(true);
        $this->pruneObsoleteSchema(true);
        $this->checkConfigDefaults(true);
        $this->finalizeSchemaVersion();
        return $this->report;
    }

    public function check() {
        $this->assertWritableConfigPrefix();
        $this->report = array();
        $this->processSchema(false, false);
        $this->migrateLegacySharedConfigKeys(false);
        $this->pruneObsoleteSchema(false);
        $this->checkConfigDefaults(false);
        return $this->report;
    }

    /**
     * Drop obsolete columns/tables/config under $dbprefix only (never meldeliste_* / mit_*).
     * Logical table names resolve via SQLtable → {$dbprefix}Name.
     *
     * @param bool $apply
     */
    private function pruneObsoleteSchema($apply) {
        foreach($this->schema as $tableName => $columns) {
            $SQL = new SQLtable($tableName);
            if(!$SQL->exists()) {
                continue;
            }
            $defined = array_keys($columns);
            foreach($SQL->listColumns() as $columnName) {
                if(in_array($columnName, $defined, true)) {
                    continue;
                }
                $target = $tableName.'.'.$columnName;
                if(!$apply) {
                    $this->addReport('column', $target, 'obsolete', 'Spalte nicht mehr in DBconfig');
                    continue;
                }
                if($SQL->dropColumn($columnName)) {
                    $this->addReport('column', $target, 'removed', 'Veraltete Spalte entfernt');
                }
                else {
                    $this->addReport(
                        'column',
                        $target,
                        'error',
                        'Veraltete Spalte konnte nicht entfernt werden',
                        $SQL->getLastError()
                    );
                }
            }
        }

        // Only logical names; SQLtable prefixes with $dbprefix (never identity/mit_).
        foreach(array('PrintJob') as $obsoleteTable) {
            $SQL = new SQLtable($obsoleteTable);
            if(!$SQL->exists()) {
                continue;
            }
            if(!$apply) {
                $this->addReport('table', $obsoleteTable, 'obsolete', 'Tabelle veraltet (nur '.$GLOBALS['dbprefix'].')');
                continue;
            }
            if($SQL->dropTable()) {
                $this->addReport('table', $obsoleteTable, 'removed', 'Veraltete Tabelle entfernt');
            }
            else {
                $this->addReport(
                    'table',
                    $obsoleteTable,
                    'error',
                    'Veraltete Tabelle konnte nicht entfernt werden',
                    $SQL->getLastError()
                );
            }
        }

        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            return;
        }
        foreach($this->obsoleteConfigParams() as $param) {
            $sql = sprintf(
                "SELECT `Parameter` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            $row = $dbr ? mysqli_fetch_array($dbr) : null;
            $exists = $row && isset($row['Parameter']) && $row['Parameter'] === $param;
            if(!$exists) {
                continue;
            }
            if(!$apply) {
                $this->addReport('config', $param, 'obsolete', 'Config-Parameter veraltet');
                continue;
            }
            $delete = sprintf(
                "DELETE FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            if(mysqli_query($GLOBALS['conn'], $delete)) {
                $this->addReport('config', $param, 'removed', 'Veralteter Config-Parameter entfernt');
            }
            else {
                $this->addReport(
                    'config',
                    $param,
                    'error',
                    'Veralteter Config-Parameter konnte nicht entfernt werden',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    /**
     * Melde-RSVP / unused log-chip colors — no longer in Archiv ConfigDefaults.
     * Storage (Archiv*) and bare logical names (if still present).
     *
     * @return string[]
     */
    private function obsoleteConfigParams() {
        return array(
            'ArchivColorBtnYes',
            'ArchivColorBtnNo',
            'ArchivColorBtnMaybe',
            'ArchivColorDisabled',
            'ArchivColorLogDefault',
            'ArchivColorLogDBDelete',
            'ArchivColorLogDBInsert',
            'ArchivColorLogDBUpdate',
            'ArchivColorLogEmail',
            'ArchivColorLogInfo',
            'colorBtnYes',
            'colorBtnNo',
            'colorBtnMaybe',
            'colorDisabled',
            'colorLogDefault',
            'colorLogDBDelete',
            'colorLogDBInsert',
            'colorLogDBUpdate',
            'colorLogEmail',
            'colorLogInfo',
        );
    }

    /**
     * Move Melde-colliding bare keys to Archiv* in own config table.
     * @param bool $apply
     */
    private function migrateLegacySharedConfigKeys($apply) {
        if(!function_exists('archivConfigAliases')) {
            return;
        }
        $aliases = archivConfigAliases();
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            return;
        }
        foreach($aliases as $legacy => $archivKey) {
            // SchemaVersion is handled by finalizeSchemaVersion / ArchivSchemaVersion
            if($legacy === 'SchemaVersion') {
                continue;
            }
            $sqlArchiv = sprintf(
                "SELECT `Value`, `Type`, `Description` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $archivKey)
            );
            $dbrA = mysqli_query($GLOBALS['conn'], $sqlArchiv);
            $rowA = $dbrA ? mysqli_fetch_assoc($dbrA) : null;

            $sqlLegacy = sprintf(
                "SELECT `Value`, `Type`, `Description` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $legacy)
            );
            $dbrL = mysqli_query($GLOBALS['conn'], $sqlLegacy);
            $rowL = $dbrL ? mysqli_fetch_assoc($dbrL) : null;

            if(!$rowL) {
                continue;
            }

            if(!$apply) {
                $this->addReport(
                    'config',
                    $legacy,
                    'obsolete',
                    'Legacy-Key — migrieren nach '.$archivKey
                );
                continue;
            }

            if(!$rowA) {
                $insert = sprintf(
                    "INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%s', '%s', '%s');",
                    $GLOBALS['dbprefix'],
                    mysqli_real_escape_string($GLOBALS['conn'], $archivKey),
                    mysqli_real_escape_string($GLOBALS['conn'], (string)$rowL['Value']),
                    mysqli_real_escape_string($GLOBALS['conn'], isset($rowL['Type']) ? (string)$rowL['Type'] : 'string'),
                    mysqli_real_escape_string(
                        $GLOBALS['conn'],
                        isset($rowL['Description']) ? (string)$rowL['Description'] : $archivKey
                    )
                );
                if(mysqli_query($GLOBALS['conn'], $insert)) {
                    $this->addReport('config', $archivKey, 'created', 'Migriert von '.$legacy);
                }
                else {
                    $this->addReport('config', $archivKey, 'error', 'Migration von '.$legacy.' fehlgeschlagen');
                    continue;
                }
            }

            $del = sprintf(
                "DELETE FROM `%sconfig` WHERE `Parameter` = '%s';",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $legacy)
            );
            if(mysqli_query($GLOBALS['conn'], $del)) {
                $this->addReport('config', $legacy, 'removed', 'Legacy-Key entfernt (jetzt '.$archivKey.')');
            }
        }
    }

    /**
     * Refuse to touch Melde (identity) config/tables via a mis-set dbprefix.
     */
    private function assertWritableConfigPrefix() {
        $prefix = isset($GLOBALS['dbprefix']) ? (string)$GLOBALS['dbprefix'] : '';
        $identity = function_exists('identityPrefix')
            ? (string)identityPrefix()
            : (isset($GLOBALS['identityPrefix']) ? (string)$GLOBALS['identityPrefix'] : '');
        if($prefix === '') {
            throw new RuntimeException('SchemaManager: $dbprefix is empty — refusing schema ops.');
        }
        if($identity !== '' && $prefix === $identity) {
            throw new RuntimeException(
                'SchemaManager: $dbprefix equals $identityPrefix ('.$prefix.') — '
                .'would overwrite Melde config/schema. Same MySQL DB is fine; '
                .'set $dbprefix to a distinct Archiv prefix (e.g. archiv_ / archiv-dev_) '
                .'and keep $identityPrefix as Melde\'s prefix (e.g. meldeliste_ / meldeliste-dev_).'
            );
        }
    }

    /** Config key for Archiv schema version (never Melde's SchemaVersion). */
    public static function schemaVersionParam() {
        return 'ArchivSchemaVersion';
    }

    public function getExpectedSchemaVersion($forceReload = false) {
        if(!function_exists('archivGetExpectedSchemaVersion')) {
            require_once dirname(__DIR__).'/config/SchemaVersion.php';
        }
        return (int)archivGetExpectedSchemaVersion($forceReload);
    }

    public function getInstalledSchemaVersion() {
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            return 0;
        }
        $param = self::schemaVersionParam();
        $sql = sprintf(
            "SELECT `Value` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
            $GLOBALS['dbprefix'],
            mysqli_real_escape_string($GLOBALS['conn'], $param)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        $row = $dbr ? mysqli_fetch_array($dbr) : null;
        if($row && isset($row['Value'])) {
            return (int)$row['Value'];
        }
        // Legacy fallback within own prefix only (pre-ARCHIVSchemaVersion key)
        $sqlLegacy = sprintf(
            "SELECT `Value` FROM `%sconfig` WHERE `Parameter` = 'SchemaVersion' LIMIT 1;",
            $GLOBALS['dbprefix']
        );
        $dbrLegacy = mysqli_query($GLOBALS['conn'], $sqlLegacy);
        $rowLegacy = $dbrLegacy ? mysqli_fetch_array($dbrLegacy) : null;
        if($rowLegacy && isset($rowLegacy['Value'])) {
            return (int)$rowLegacy['Value'];
        }
        return 0;
    }

    public function isSchemaOutdated($forceReload = false) {
        return $this->getInstalledSchemaVersion() < $this->getExpectedSchemaVersion($forceReload);
    }

    public function setInstalledSchemaVersion($version) {
        $this->assertWritableConfigPrefix();
        $version = (int)$version;
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            $this->addReport('data', self::schemaVersionParam(), 'error', 'config-Tabelle fehlt — Version nicht gesetzt');
            return false;
        }
        $param = self::schemaVersionParam();
        $sql = sprintf(
            "SELECT `Parameter` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
            $GLOBALS['dbprefix'],
            mysqli_real_escape_string($GLOBALS['conn'], $param)
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        $row = $dbr ? mysqli_fetch_array($dbr) : null;
        $exists = $row && isset($row['Parameter']) && $row['Parameter'] === $param;

        if($exists) {
            $update = sprintf(
                "UPDATE `%sconfig` SET `Value` = '%d' WHERE `Parameter` = '%s';",
                $GLOBALS['dbprefix'],
                $version,
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            $ok = mysqli_query($GLOBALS['conn'], $update);
        }
        else {
            $insert = sprintf(
                "INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%d', 'int', '%s');",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param),
                $version,
                mysqli_real_escape_string(
                    $GLOBALS['conn'],
                    'Installierte Archiv-Schema-Version (Soll: config/schema_version_number.php); nicht Melde SchemaVersion'
                )
            );
            $ok = mysqli_query($GLOBALS['conn'], $insert);
        }

        if($ok) {
            // Drop legacy key in own config table so Melde's Parameter name is never mirrored here.
            $del = sprintf(
                "DELETE FROM `%sconfig` WHERE `Parameter` = 'SchemaVersion';",
                $GLOBALS['dbprefix']
            );
            @mysqli_query($GLOBALS['conn'], $del);

            if(isset($GLOBALS['optionsDB']) && is_array($GLOBALS['optionsDB'])) {
                $GLOBALS['optionsDB'][$param] = (string)$version;
                unset($GLOBALS['optionsDB']['SchemaVersion']);
            }
            return true;
        }
        $this->addReport(
            'data',
            $param,
            'error',
            $param.' konnte nicht gespeichert werden',
            mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
        );
        return false;
    }

    private function finalizeSchemaVersion() {
        $expected = $this->getExpectedSchemaVersion();
        $installed = $this->getInstalledSchemaVersion();
        $param = self::schemaVersionParam();
        if($this->hasErrors()) {
            $this->addReport(
                'data',
                $param,
                'mismatch',
                sprintf('Version nicht gesetzt (Fehler vorhanden). Installiert: %d, Soll: %d', $installed, $expected)
            );
            return;
        }
        if($installed === $expected) {
            // Still migrate legacy SchemaVersion → ArchivSchemaVersion if needed
            $this->setInstalledSchemaVersion($expected);
            $this->addReport('data', $param, 'ok', 'Schema-Version '.$expected);
            return;
        }
        if($this->setInstalledSchemaVersion($expected)) {
            $this->addReport(
                'data',
                $param,
                'fixed',
                sprintf('Schema-Version %d → %d', $installed, $expected)
            );
        }
    }

    /**
     * @param bool $applyCreate create missing tables/columns
     * @param bool $applyRepair modify mismatched column definitions
     */
    private function processSchema($applyCreate, $applyRepair) {
        foreach($this->schema as $tableName => $columns) {
            $SQL = new SQLtable($tableName);

            if(!$SQL->exists()) {
                if($applyCreate) {
                    $result = $SQL->create();
                    if($result === true) {
                        $this->addReport('table', $tableName, 'created', 'Tabelle angelegt');
                    }
                    else {
                        $this->addReport('table', $tableName, 'error', 'Tabelle konnte nicht angelegt werden', $SQL->getLastError());
                        continue;
                    }
                }
                else {
                    $this->addReport('table', $tableName, 'missing', 'Tabelle fehlt');
                    continue;
                }
            }
            else {
                $this->addReport('table', $tableName, 'ok', 'Tabelle vorhanden');
            }

            foreach($columns as $columnName => $definition) {
                $target = $tableName.'.'.$columnName;
                if(!$SQL->columnExists($columnName)) {
                    if($applyCreate) {
                        $result = $SQL->createColumn($columnName, $definition);
                        if($result === true) {
                            $this->addReport('column', $target, 'created', 'Spalte angelegt');
                        }
                        elseif($result === -1) {
                            $this->addReport('column', $target, 'ok', 'Spalte vorhanden');
                        }
                        else {
                            $this->addReport('column', $target, 'error', 'Spalte konnte nicht angelegt werden', $SQL->getLastError());
                        }
                    }
                    else {
                        $this->addReport('column', $target, 'missing', 'Spalte fehlt');
                    }
                    continue;
                }

                $diffs = $SQL->compareColumn($columnName, $definition);
                if(empty($diffs)) {
                    $this->addReport('column', $target, 'ok', 'Spalte ok');
                    continue;
                }

                if($applyRepair) {
                    if($SQL->modifyColumn($columnName, $definition)) {
                        $newDiffs = $SQL->compareColumn($columnName, $definition);
                        if(empty($newDiffs)) {
                            $this->addReport('column', $target, 'fixed', 'Spalte angepasst', $diffs);
                        }
                        else {
                            $this->addReport('column', $target, 'mismatch', 'Abweichung nach Repair noch vorhanden', $newDiffs);
                        }
                    }
                    else {
                        $this->addReport('column', $target, 'error', 'Spalte konnte nicht angepasst werden', $SQL->getLastError());
                    }
                }
                else {
                    $this->addReport('column', $target, 'mismatch', 'Spalte weicht ab', $diffs);
                }
            }
        }
    }

    private function checkConfigDefaults($apply) {
        if(!function_exists('getConfigDefaults')) {
            require_once dirname(__DIR__).'/config/ConfigDefaults.php';
        }
        $defaults = getConfigDefaults();
        $configTable = new SQLtable('config');
        if(!$configTable->exists()) {
            $this->addReport('config', 'config', 'missing', 'config-Tabelle fehlt — Defaults übersprungen');
            return;
        }

        foreach($defaults as $item) {
            $param = $item['Parameter'];
            $sql = sprintf(
                "SELECT `Parameter` FROM `%sconfig` WHERE `Parameter` = '%s' LIMIT 1;",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param)
            );
            $dbr = mysqli_query($GLOBALS['conn'], $sql);
            $row = $dbr ? mysqli_fetch_array($dbr) : null;
            $exists = $row && isset($row['Parameter']) && $row['Parameter'] === $param;

            if($exists) {
                $this->addReport('config', $param, 'ok', 'Config-Parameter vorhanden');
                continue;
            }

            if(!$apply) {
                $this->addReport('config', $param, 'missing', 'Config-Parameter fehlt');
                continue;
            }

            $insert = sprintf(
                "INSERT INTO `%sconfig` (`Parameter`, `Value`, `Type`, `Description`) VALUES ('%s', '%s', '%s', '%s');",
                $GLOBALS['dbprefix'],
                mysqli_real_escape_string($GLOBALS['conn'], $param),
                mysqli_real_escape_string($GLOBALS['conn'], (string)$item['Value']),
                mysqli_real_escape_string($GLOBALS['conn'], $item['Type']),
                mysqli_real_escape_string($GLOBALS['conn'], $item['Description'])
            );
            $ok = mysqli_query($GLOBALS['conn'], $insert);
            if($ok) {
                $this->addReport('config', $param, 'created', 'Config-Parameter eingefügt');
            }
            else {
                $this->addReport(
                    'config',
                    $param,
                    'error',
                    'Config-Parameter konnte nicht eingefügt werden',
                    mysqli_errno($GLOBALS['conn']).': '.mysqli_error($GLOBALS['conn'])
                );
            }
        }
    }

    public function formatReportText() {
        $lines = array();
        foreach($this->report as $entry) {
            $line = strtoupper($entry['status'])."\t[".$entry['level']."]\t".$entry['target'];
            if($entry['message']) $line .= "\t".$entry['message'];
            if($entry['detail'] && is_string($entry['detail'])) $line .= "\t".$entry['detail'];
            if($entry['detail'] && is_array($entry['detail'])) {
                $line .= "\t".json_encode($entry['detail']);
            }
            $lines[] = $line;
        }
        return implode("\n", $lines)."\n";
    }
}
?>
