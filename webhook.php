<?php
/**
 * Receives the browser report (JSON string or plain text) and appends it to
 * data.txt, which hound.sh tails live.
 */

$raw = file_get_contents('php://input');

$text = '';
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_string($decoded)) {
        $text = $decoded;
    } elseif (is_array($decoded)) {
        $text = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        $text = $raw;
    }
}

$text = trim($text);

header('Content-Type: text/plain; charset=utf-8');

if ($text !== '') {
    $entry = "===== HOUND REPORT " . gmdate('Y-m-d H:i:s') . " UTC =====\n" . $text . "\n\n";
    file_put_contents(__DIR__ . '/data.txt', $entry, FILE_APPEND | LOCK_EX);
    echo "ok";
} else {
    echo "empty";
}
