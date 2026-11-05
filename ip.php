<?php
/**
 * Logs the visitor IP, user agent and (when possible) IP based geolocation
 * into ip.txt. hound.sh watches that file and prints the report live.
 *
 * Set HOUND_GEOIP=0 to skip the external ip-api.com lookup.
 */

function hound_client_ip()
{
    $keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    foreach ($keys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        $value = trim((string) $_SERVER[$key]);
        if ($key === 'HTTP_X_FORWARDED_FOR' || $key === 'HTTP_CLIENT_IP') {
            $parts = explode(',', $value);
            $value = trim($parts[0]);
        }
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
}

function hound_ip_geo($ip)
{
    if (getenv('HOUND_GEOIP') === '0') {
        return array();
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return array();
    }
    if (!ini_get('allow_url_fopen')) {
        return array();
    }

    $url = 'http://ip-api.com/json/' . rawurlencode($ip)
        . '?fields=status,country,countryCode,regionName,city,zip,lat,lon,timezone,isp,org,as,query';

    $context = stream_context_create(array(
        'http' => array(
            'timeout' => 2.5,
            'ignore_errors' => true,
            'user_agent' => 'Hound/0.3',
        ),
    ));

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return array();
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['status']) || $data['status'] !== 'success') {
        return array();
    }

    return $data;
}

$ip  = hound_client_ip();
$ua  = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : 'Unknown';
$ref = isset($_SERVER['HTTP_REFERER']) ? substr((string) $_SERVER['HTTP_REFERER'], 0, 512) : '-';

$entry  = 'IP: ' . $ip . "\r\n";
$entry .= 'User-Agent: ' . $ua . "\r\n";
$entry .= 'Time: ' . gmdate('Y-m-d H:i:s') . " UTC\r\n";
$entry .= 'Referer: ' . $ref . "\r\n";

$geo = hound_ip_geo($ip);
if (!empty($geo)) {
    $place = array();
    foreach (array('city', 'regionName', 'country') as $field) {
        if (!empty($geo[$field])) {
            $place[] = $geo[$field];
        }
    }
    if (!empty($place)) {
        $suffix = !empty($geo['countryCode']) ? ' (' . $geo['countryCode'] . ')' : '';
        $entry .= 'Location: ' . implode(', ', $place) . $suffix . "\r\n";
    }
    $isp = !empty($geo['isp']) ? $geo['isp'] : '';
    if (!empty($geo['org']) && $geo['org'] !== $isp) {
        $isp .= ($isp !== '' ? ' / ' : '') . $geo['org'];
    }
    if ($isp !== '') {
        $entry .= 'ISP: ' . $isp . "\r\n";
    }
    if (isset($geo['lat'], $geo['lon'])) {
        $entry .= 'Coordinates: ' . $geo['lat'] . ', ' . $geo['lon'] . "\r\n";
        $entry .= 'Google Maps: https://www.google.com/maps?q=' . $geo['lat'] . ',' . $geo['lon'] . "\r\n";
    }
}

$entry .= "---\r\n";

file_put_contents(__DIR__ . '/ip.txt', $entry, FILE_APPEND | LOCK_EX);
