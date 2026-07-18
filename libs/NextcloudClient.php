<?php
/**
 * Nextcloud WebDAV client stub (config-driven).
 * TODO: implement PROPFIND list and GET download with proper auth headers.
 */
class NextcloudClient
{
    private $baseUrl;
    private $user;
    private $password;

    public function __construct($options = null) {
        if($options === null && isset($GLOBALS['optionsDB'])) {
            $options = $GLOBALS['optionsDB'];
        }
        if(!is_array($options)) {
            $options = array();
        }
        $this->baseUrl = rtrim((string)($options['nextcloudBaseUrl'] ?? ''), '/');
        $this->user = (string)($options['nextcloudUser'] ?? '');
        $this->password = (string)($options['nextcloudAppPassword'] ?? ($options['nextcloudPassword'] ?? ''));
    }

    public function isConfigured() {
        return $this->baseUrl !== '' && $this->user !== '' && $this->password !== '';
    }

    /**
     * @return array<int, array<string, string>>|false
     * TODO: parse WebDAV PROPFIND response
     */
    public function listFolder($remotePath) {
        if(!$this->isConfigured()) {
            return false;
        }
        $url = $this->baseUrl.'/'.ltrim($remotePath, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_USERPWD => $this->user.':'.$this->password,
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array('Depth: 1'),
            CURLOPT_TIMEOUT => 30,
        ));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($code < 200 || $code >= 300) {
            return false;
        }
        // TODO: parse XML listing; return stub empty list for now
        return array();
    }

    /**
     * @return string|false file contents
     * TODO: stream large files to disk
     */
    public function download($remotePath) {
        if(!$this->isConfigured()) {
            return false;
        }
        $url = $this->baseUrl.'/'.ltrim($remotePath, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_USERPWD => $this->user.':'.$this->password,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($code < 200 || $code >= 300) {
            return false;
        }
        return $body;
    }
}
?>
