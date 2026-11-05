<?php
/**
 * Hound entry point (v0.3).
 *
 * Modes (HOUND_MODE):
 *   consent -> share.php, the explicit "share my location" page.
 *              Nothing is collected before the visitor taps the button.
 *   track   -> classic tracked redirect page (tracker.php), only used when
 *              HOUND_TARGET_URL is set.
 *
 * Without HOUND_TARGET_URL the built-in Hound page is served.
 */

$target = getenv('HOUND_TARGET_URL');
$mode   = strtolower(trim((string) getenv('HOUND_MODE')));

if ($mode === 'consent') {
    include __DIR__ . '/share.php';
    exit;
}

include __DIR__ . '/ip.php';

if ($target !== false && trim($target) !== '') {
    include __DIR__ . '/tracker.php';
    exit;
}

header('Location: index.html');
exit;
