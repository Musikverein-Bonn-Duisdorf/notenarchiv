<?php
/**
 * Stimmsatz: resolve score files for a rehearsal phase + instrument + voice.
 * Print mode: single-sided pages, then glue/bind (Duplex off).
 * UserVoice auto-selection: TODO (manual voice_label for now).
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
