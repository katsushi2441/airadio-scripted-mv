<?php
function airadio_load_config($yaml_path) {
    if (!file_exists($yaml_path)) { return array(); }
    $config = array();
    foreach (file($yaml_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*#/', $line) || trim($line) === '') { continue; }
        if (preg_match('/^([A-Z0-9_]+):\s*(.+)$/', $line, $m)) {
            $config[$m[1]] = trim($m[2], " \t\"'");
        }
    }
    return $config;
}

$airadio_config = airadio_load_config(__DIR__ . '/config.yaml');

define('AIRADIO_LYRICS_API', $airadio_config['AIRADIO_LYRICS_API'] ?? getenv('AIRADIO_LYRICS_API') ?: 'http://exbridge.ddns.net:18201');
define('AIGM_BASE_URL', $airadio_config['AIGM_BASE_URL'] ?? 'https://aiknowledgecms.exbridge.jp');
define('AIGM_COOKIE_DOMAIN', $airadio_config['AIGM_COOKIE_DOMAIN'] ?? '.exbridge.jp');
define('AIGM_ADMIN', $airadio_config['AIGM_ADMIN'] ?? 'xb_bittensor');

