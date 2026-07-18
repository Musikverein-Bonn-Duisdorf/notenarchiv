<?php
/**
 * Stimmsatz: resolve score files for a rehearsal phase + instrument + voice.
 * Print mode: single-sided pages, then glue/bind (Duplex off).
 * UserVoice: optional load from meldeliste_UserVoice (primary + fallbacks).
 */
class Stimmsatz
{
    private $phaseId;
    private $instrumentId;
    private $voiceLabel;

    public function __construct($phaseId, $instrumentId, $voiceLabel) {
        $this->phaseId = (int)$phaseId;
        $this->instrumentId = (int)$instrumentId;
        $this->voiceLabel = trim((string)$voiceLabel);
    }

    /**
     * @return array<int, array{composition: Composition, scoreFiles: ScoreFile[]}>
     */
    public function resolvePieces() {
        $phase = new RehearsalPhase();
        $phase->load_by_id($this->phaseId);
        if(!$phase->Index) {
            return array();
        }

        $result = array();
        foreach($phase->listCompositions() as $composition) {
            $files = ScoreFile::listForCompositionInstrumentVoice(
                $composition->Index,
                $this->instrumentId,
                $this->voiceLabel
            );
            if(empty($files)) {
                continue;
            }
            $result[] = array(
                'composition' => $composition,
                'scoreFiles' => $files,
            );
        }
        return $result;
    }

    /**
     * Try primary voice then fallbacks from Meldeliste UserVoice table.
     *
     * @return array{pieces: array, instrumentId: int, voiceLabel: string, usedFallback: bool}|null
     */
    public function resolvePiecesWithUserVoice($userId) {
        $candidates = self::userVoiceCandidates($userId);
        if(empty($candidates)) {
            return null;
        }
        foreach($candidates as $candidate) {
            $trial = new self($this->phaseId, (int)$candidate['instrument'], (string)$candidate['voice']);
            $pieces = $trial->resolvePieces();
            if(!empty($pieces)) {
                return array(
                    'pieces' => $pieces,
                    'instrumentId' => (int)$candidate['instrument'],
                    'voiceLabel' => (string)$candidate['voice'],
                    'usedFallback' => ((int)$candidate['priority'] > 0),
                );
            }
        }
        return null;
    }

    /**
     * @return array<int, array{instrument:int, voice:string, priority:int}>
     */
    public static function userVoiceCandidates($userId) {
        $userId = (int)$userId;
        if($userId < 1 || !self::userVoiceTableExists()) {
            return array();
        }
        $table = identityPrefix().'UserVoice';
        $sql = sprintf(
            'SELECT `Instrument`, `VoiceLabel`, `Priority` FROM `%s` WHERE `User` = %d AND `Active` = 1 ORDER BY `Priority` ASC, `Index` ASC;',
            $table,
            $userId
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        if(!$dbr) {
            return array();
        }
        $out = array();
        while($row = mysqli_fetch_assoc($dbr)) {
            $out[] = array(
                'instrument' => (int)$row['Instrument'],
                'voice' => (string)$row['VoiceLabel'],
                'priority' => (int)$row['Priority'],
            );
        }
        return $out;
    }

    public static function userVoiceTableExists() {
        static $cached = null;
        if($cached !== null) {
            return $cached;
        }
        $table = identityPrefix().'UserVoice';
        $schema = mysqli_real_escape_string($GLOBALS['conn'], $GLOBALS['sql']['database'] ?? '');
        $name = mysqli_real_escape_string($GLOBALS['conn'], $table);
        $sql = sprintf(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' LIMIT 1;",
            $schema,
            $name
        );
        $dbr = @mysqli_query($GLOBALS['conn'], $sql);
        $cached = ($dbr && mysqli_fetch_array($dbr)) ? true : false;
        return $cached;
    }

    public function getPrintModeNote() {
        return 'Druckmodus: einseitig (Simplex), danach Stimmen heften/kleben. Duplex aus.';
    }

    public function getVoiceLabel() {
        return $this->voiceLabel;
    }

    public function getInstrumentId() {
        return $this->instrumentId;
    }

    public function getPhaseId() {
        return $this->phaseId;
    }
}
?>
