<?php
/**
 * Consent based location share page (HOUND_MODE=consent).
 *
 * This page is intentionally open about what it does:
 *   - it says which data is collected and who receives it
 *   - nothing is requested or sent until the visitor taps "আমার লোকেশন শেয়ার করুন"
 *   - the destination is shown, not masked
 *   - no IP logging, no silent redirect, no background tracking
 *
 * A browser cannot switch the phone's Location setting on. When location is
 * unavailable the page explains what the visitor has to enable and offers a
 * shortcut to the Android location settings screen.
 */

$target = getenv('HOUND_TARGET_URL');
$target = $target === false ? '' : trim($target);

$target_host = '';
if ($target !== '') {
    $parsed = parse_url($target, PHP_URL_HOST);
    $target_host = $parsed ? $parsed : $target;
}

$recipient = $target_host !== '' ? $target_host : 'এই সাইটের পরিচালক';

$json_flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>লোকেশন শেয়ার · Location share</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef2f8;
    color: #1f2937;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans Bengali", "Nirmala UI", Roboto, Arial, sans-serif;
    padding: 20px;
    line-height: 1.55;
  }
  .card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 12px 34px rgba(15, 23, 42, .09);
    padding: 26px 22px 22px;
  }
  .badge {
    display: inline-block;
    font-size: 11px;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #1d4ed8;
    background: #e8efff;
    border-radius: 999px;
    padding: 4px 10px;
    margin-bottom: 12px;
  }
  h1 { margin: 0 0 10px; font-size: 21px; line-height: 1.35; }
  p { margin: 0 0 10px; font-size: 14px; color: #374151; }
  .lead b { color: #111827; }
  .facts {
    margin: 4px 0 18px;
    padding: 12px 14px 12px 30px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    font-size: 13px;
    color: #374151;
  }
  .facts li { margin: 4px 0; }
  .actions { display: flex; flex-direction: column; gap: 9px; }
  button, .btn {
    font: inherit;
    font-size: 15px;
    font-weight: 600;
    border-radius: 10px;
    padding: 12px 16px;
    border: 1px solid transparent;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    display: block;
    width: 100%;
  }
  .primary { background: #2563eb; color: #fff; }
  .primary:disabled { background: #9db8f1; cursor: default; }
  .ghost { background: #fff; color: #374151; border-color: #d1d5db; }
  button:focus-visible, .btn:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
  .status { margin: 12px 0 0; font-size: 13px; color: #6b7280; min-height: 18px; }
  .error {
    margin: 12px 0 0;
    font-size: 13px;
    color: #991b1b;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    padding: 10px 12px;
  }
  .fix {
    margin: 12px 0 0;
    font-size: 13px;
    color: #374151;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 12px 14px;
  }
  .fix .fix-title { font-weight: 600; margin: 0 0 6px; }
  .fix ol { margin: 0; padding-left: 20px; }
  .fix li { margin: 4px 0; }
  .result {
    margin: 14px 0 0;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 14px;
  }
  .ok-line { font-weight: 600; color: #166534; font-size: 14px; margin-bottom: 8px; }
  .coords { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; margin: 0; font-size: 13px; }
  .coords dt { color: #4b5563; }
  .coords dd { margin: 0; font-variant-numeric: tabular-nums; word-break: break-all; }
  .maplink { display: inline-block; margin-top: 10px; font-size: 13px; color: #1d4ed8; }
  .livebar {
    margin: 12px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #065f46;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    padding: 9px 12px;
  }
  .dot {
    width: 9px; height: 9px; border-radius: 50%; background: #10b981;
    animation: pulse 1.6s ease-in-out infinite;
  }
  @keyframes pulse { 50% { opacity: .25; } }
  .linkbtn {
    margin-left: auto;
    background: none;
    border: 0;
    color: #b91c1c;
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    padding: 0;
    width: auto;
    cursor: pointer;
    text-decoration: underline;
  }
  .hint { margin: 16px 0 0; font-size: 11.5px; color: #6b7280; }
  .hint a { color: #1d4ed8; }
  [hidden] { display: none !important; }
</style>
</head>
<body>
<main class="card">
  <div class="badge">সম্মতিভিত্তিক · Consent based</div>

  <h1>আপনার লোকেশন শেয়ার করবেন?</h1>

  <p class="lead">
    আপনি নিচের বাটনে চাপ দিলে এবং ব্রাউজার অনুমতি দিলে আপনার <b>বর্তমান অবস্থান</b>
    <b><?php echo htmlspecialchars($recipient, ENT_QUOTES, 'UTF-8'); ?></b>-এর কাছে পাঠানো হবে।
    আপনি অনুমতি না দিলে <b>কিছুই পাঠানো হবে না</b>।
  </p>

  <ul class="facts">
    <li><b>যা পাঠানো হবে:</b> অক্ষাংশ, দ্রাঘিমাংশ, নির্ভুলতা (accuracy) এবং সময়।</li>
    <li><b>কেন:</b> আপনি যে পেজটি দেখতে যাচ্ছেন সেটি চালানোর আগে অবস্থান জানা।</li>
    <li><b>না চাইলে:</b> নিচের দ্বিতীয় বাটনে চাপ দিন, কোনো তথ্য যাবে না।</li>
  </ul>

  <div class="actions" id="initial">
    <button type="button" class="primary" id="share-btn">আমার লোকেশন শেয়ার করুন</button>
    <button type="button" class="ghost" id="skip-btn">না, শুধু ভিডিও দেখুন</button>
  </div>

  <div class="actions" id="done" hidden>
    <button type="button" class="ghost" id="live-btn">১ মিনিট লাইভ আপডেট</button>
    <button type="button" class="primary" id="go-btn">এগিয়ে যান</button>
  </div>

  <p class="status" id="status" role="status" aria-live="polite"></p>

  <p class="error" id="error" role="alert" aria-live="assertive" hidden></p>

  <div class="fix" id="fix" hidden></div>

  <div class="result" id="result" hidden>
    <div class="ok-line">✓ লোকেশন পাঠানো হয়েছে</div>
    <dl class="coords">
      <dt>Latitude</dt><dd id="lat">-</dd>
      <dt>Longitude</dt><dd id="lon">-</dd>
      <dt>Accuracy</dt><dd id="acc">-</dd>
    </dl>
    <a class="maplink" id="map-link" href="#" target="_blank" rel="noopener noreferrer" hidden>Google Maps-এ দেখুন ↗</a>
  </div>

  <div class="livebar" id="livebar" hidden>
    <span class="dot" aria-hidden="true"></span>
    <span>লাইভ আপডেট চলছে (<span id="live-count">0</span>)</span>
    <button type="button" class="linkbtn" id="stop-live">বন্ধ করুন</button>
  </div>

  <a class="btn ghost" id="android-settings" hidden
     href="intent://#Intent;action=android.settings.LOCATION_SOURCE_SETTINGS;end">Android Location সেটিংস খুলুন</a>

  <p class="hint" id="perm-note"></p>

  <p class="hint">
    এই পেজ নিজে কোনো IP লগ করে না। শুধু আপনি অনুমতি দিলে অবস্থান রেকর্ড হয়, আর
    আপনি যেকোনো সময় বন্ধ করতে পারেন।
  </p>
</main>

<noscript>
  <p style="max-width:440px;margin:12px auto;font-size:14px;">
    এই পেজে লোকেশন শেয়ার করতে JavaScript দরকার। ব্রাউজারে JavaScript চালু করুন।
  </p>
</noscript>

<script>
(function () {
  "use strict";

  var TARGET = <?php echo json_encode($target, $json_flags); ?>;
  var HAS_TARGET = TARGET !== "";

  var el = function (id) { return document.getElementById(id); };

  var shareBtn  = el("share-btn");
  var skipBtn   = el("skip-btn");
  var initial   = el("initial");
  var doneBox   = el("done");
  var resultBox = el("result");
  var errorBox  = el("error");
  var fixBox    = el("fix");
  var statusEl  = el("status");
  var permNote  = el("perm-note");
  var liveBar   = el("livebar");
  var liveCount = el("live-count");
  var mapLink   = el("map-link");
  var goBtn     = el("go-btn");
  var liveBtn   = el("live-btn");
  var stopBtn   = el("stop-live");
  var androidBtn= el("android-settings");
  var latOut    = el("lat");
  var lonOut    = el("lon");
  var accOut    = el("acc");

  var LABEL_SHARE = "আমার লোকেশন শেয়ার করুন";

  var busy = false;
  var countdown = null;
  var watchId = null;
  var liveTimer = null;
  var permState = "unknown";

  var ua = navigator.userAgent || "";
  var isAndroid = /Android/i.test(ua);
  var isIOS = /iPhone|iPad|iPod/i.test(ua);

  if (!HAS_TARGET) {
    skipBtn.hidden = true;
    goBtn.hidden = true;
  }

  /* ---------- helpers ---------- */

  function say(message) { statusEl.textContent = message || ""; }

  function showError(message) {
    errorBox.hidden = false;
    errorBox.textContent = message;
  }

  function clearError() {
    errorBox.hidden = true;
    errorBox.textContent = "";
  }

  function renderFix(title, items) {
    fixBox.hidden = false;
    fixBox.innerHTML = "";
    var head = document.createElement("p");
    head.className = "fix-title";
    head.textContent = title;
    fixBox.appendChild(head);
    var list = document.createElement("ol");
    for (var i = 0; i < items.length; i += 1) {
      var li = document.createElement("li");
      li.textContent = items[i];
      list.appendChild(li);
    }
    fixBox.appendChild(list);
  }

  function setBusy(on) {
    busy = on;
    shareBtn.disabled = on;
    shareBtn.textContent = on ? "লোকেশন চাওয়া হচ্ছে…" : LABEL_SHARE;
    if (on) { say("ব্রাউজারের অনুমতি ও GPS ফিক্সের জন্য অপেক্ষা করা হচ্ছে…"); }
  }

  function go() {
    if (!HAS_TARGET) { return; }
    stopLive();
    stopCountdown();
    window.location.replace(TARGET);
  }

  /* ---------- result rendering ---------- */

  function showResult(coords) {
    resultBox.hidden = false;
    latOut.textContent = coords.latitude != null ? String(coords.latitude) : "-";
    lonOut.textContent = coords.longitude != null ? String(coords.longitude) : "-";
    accOut.textContent = coords.accuracy != null ? Math.round(coords.accuracy) + " m" : "-";
    if (coords.latitude != null && coords.longitude != null) {
      mapLink.href = "https://www.google.com/maps/place/" + coords.latitude + "," + coords.longitude;
      mapLink.hidden = false;
    }
  }

  function buildReport(coords, kind) {
    var lat = coords.latitude;
    var lon = coords.longitude;
    return "Hound - Consent Location Share\n"
      + "------------------------------------\n\n"
      + "Consent: visitor tapped \"" + LABEL_SHARE + "\" and the browser granted it.\n"
      + "Report: " + (kind === "live" ? "live update" : "initial fix") + "\n"
      + "Latitude: " + lat + "\n"
      + "Longitude: " + lon + "\n"
      + "Accuracy: " + (coords.accuracy != null ? Math.round(coords.accuracy) + " m" : "-") + "\n"
      + "Altitude: " + (coords.altitude != null ? Math.round(coords.altitude) + " m" : "-") + "\n"
      + "Map Location: https://www.google.com/maps/place/" + lat + "," + lon + "\n"
      + "Time: " + new Date().toISOString() + "\n"
      + "Page: " + window.location.href + "\n"
      + "------------------\n";
  }

  function sendReport(text) {
    var body = JSON.stringify(text);
    if (window.fetch) {
      return fetch("webhook.php", {
        method: "POST",
        headers: { "Content-Type": "application/json; charset=utf-8" },
        body: body
      }).then(function (res) {
        if (!res.ok) { throw new Error("HTTP " + res.status); }
        return res.text();
      });
    }
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open("POST", "webhook.php", true);
      xhr.setRequestHeader("Content-Type", "application/json; charset=utf-8");
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) { resolve(xhr.responseText); }
        else { reject(new Error("HTTP " + xhr.status)); }
      };
      xhr.onerror = function () { reject(new Error("network")); };
      xhr.send(body);
    });
  }

  /* ---------- continue countdown ---------- */

  function startCountdown(seconds) {
    stopCountdown();
    if (!HAS_TARGET) { return; }
    var left = seconds;
    goBtn.textContent = "এগিয়ে যান (" + left + ")";
    countdown = setInterval(function () {
      left -= 1;
      if (left <= 0) {
        stopCountdown();
        go();
        return;
      }
      goBtn.textContent = "এগিয়ে যান (" + left + ")";
    }, 1000);
  }

  function stopCountdown() {
    if (countdown) { clearInterval(countdown); countdown = null; }
    goBtn.textContent = "এগিয়ে যান";
  }

  /* ---------- live sharing (explicit, can be stopped) ---------- */

  function startLive() {
    if (watchId !== null) { return; }
    stopCountdown();
    liveBar.hidden = false;
    liveBtn.textContent = "লোকেশন বন্ধ করুন";
    var count = 0;

    watchId = navigator.geolocation.watchPosition(function (pos) {
      var coords = pos.coords || {};
      count += 1;
      liveCount.textContent = String(count);
      showResult(coords);
      sendReport(buildReport(coords, "live")).catch(function () {});
    }, function () {
      /* one failed update must not end the whole session */
    }, {
      enableHighAccuracy: true,
      maximumAge: 15000,
      timeout: 30000
    });

    liveTimer = setTimeout(stopLive, 60000);
  }

  function stopLive() {
    if (watchId !== null) {
      try { navigator.geolocation.clearWatch(watchId); } catch (e) { /* ignore */ }
      watchId = null;
    }
    if (liveTimer) { clearTimeout(liveTimer); liveTimer = null; }
    liveBar.hidden = true;
    liveBtn.textContent = "১ মিনিট লাইভ আপডেট";
  }

  /* ---------- geolocation ---------- */

  function onPosition(pos) {
    setBusy(false);
    clearError();
    fixBox.hidden = true;
    var coords = pos.coords || {};
    showResult(coords);
    say("লোকেশন পাওয়া গেছে, সার্ভারে পাঠানো হচ্ছে…");

    sendReport(buildReport(coords, "initial")).then(function () {
      say("লোকেশন পাঠানো হয়েছে।");
      initial.hidden = true;
      doneBox.hidden = false;
      if (HAS_TARGET && watchId === null) { startCountdown(6); }
    }).catch(function () {
      showError("লোকেশন পাওয়া গেছে, কিন্তু সার্ভারে পাঠানো যায়নি (নেটওয়ার্ক সমস্যা)। "
        + "আবার চেষ্টা করতে উপরের বাটনে চাপ দিন।");
      say("");
    });
  }

  function onFailure(err) {
    var code = err && err.code;
    var granted = permState === "granted";
    var items;

    clearError();
    say("");

    if (code === 1) {
      showError("লোকেশন দিতে ব্রাউজার অনুমতি দিচ্ছে না — অথবা ফোনের Location সেটিংস বন্ধ।");
      if (granted) {
        items = [
          "ফোনের Settings → Location (অবস্থান) চালু করুন।",
          isIOS
            ? "iPhone: Settings → Privacy & Security → Location Services চালু করে আপনার ব্রাউজারকে \"While Using the App\" দিন।"
            : "Android: Settings → Location চালু করুন।",
          "ফিরে এসে আবার \"আমার লোকেশন শেয়ার করুন\" চাপুন।"
        ];
      } else {
        items = [
          "অ্যাড্রেস বারের পাশে 🔒 বা ⓘ আইকনে চাপ দিন → Location → Allow করুন।",
          "ফোনের Settings → Location চালু আছে কিনা দেখুন।",
          isIOS
            ? "iPhone: Settings → Privacy & Security → Location Services → Safari/আপনার ব্রাউজার।"
            : "Android: Chrome-এ location ব্লক করা থাকলে সেটিংস → Site settings → Location থেকে সরান।",
          "ফিরে এসে আবার চেষ্টা করুন।"
        ];
      }
    } else if (code === 2) {
      showError("লোকেশন পাওয়া যাচ্ছে না — ফোনের Location বন্ধ থাকতে পারে, বা GPS সিগন্যাল দুর্বল।");
      items = [
        "ফোনের Location সেটিংস চালু করুন।",
        "জানালার কাছে বা বাইরে গিয়ে আবার চেষ্টা করুন।",
        "Wi-Fi চালু রাখুন — নেটওয়ার্ক লোকেশন দ্রুত পাওয়া যায়।"
      ];
    } else {
      showError("সময় শেষ হয়ে গেছে, GPS এখনো ঠিক হয়নি (timeout)।");
      items = [
        "আবার \"আমার লোকেশন শেয়ার করুন\" চাপুন — দ্বিতীয়বার সাধারণত দ্রুত আসে।",
        "Wi-Fi চালু রাখুন, এতে নেটওয়ার্ক-ভিত্তিক লোকেশন দ্রুত পাওয়া যায়।",
        "বাইরে বা জানালার কাছে গেলে GPS দ্রুত ঠিক হয়।"
      ];
    }

    renderFix("কী করতে হবে:", items);
    androidBtn.hidden = !isAndroid;
    initial.hidden = false;
  }

  function tryLowAccuracy() {
    navigator.geolocation.getCurrentPosition(onPosition, function (err) {
      setBusy(false);
      onFailure(err);
    }, {
      enableHighAccuracy: false,
      timeout: 20000,
      maximumAge: 300000
    });
  }

  function requestLocation() {
    if (busy) { return; }
    clearError();
    fixBox.hidden = true;
    if (!navigator.geolocation) {
      showError("এই ব্রাউজার লোকেশন সাপোর্ট করে না।");
      return;
    }
    if (!window.isSecureContext) {
      showError("এই পেজটি HTTPS-এ খোলা হয়নি, তাই ব্রাউজার লোকেশন দেবে না। "
        + "টানেলের https:// লিংকটি ব্যবহার করুন।");
      return;
    }
    setBusy(true);
    navigator.geolocation.getCurrentPosition(onPosition, function (err) {
      if (err && err.code === 1) {
        setBusy(false);
        onFailure(err);
        return;
      }
      say("উচ্চ-নির্ভুলতা লোকেশন পাওয়া যায়নি, সাধারণ লোকেশন দিয়ে আবার চেষ্টা করা হচ্ছে…");
      tryLowAccuracy();
    }, {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 60000
    });
  }

  /* ---------- init ---------- */

  shareBtn.addEventListener("click", requestLocation);
  skipBtn.addEventListener("click", go);
  goBtn.addEventListener("click", go);
  liveBtn.addEventListener("click", function () {
    if (watchId !== null) { stopLive(); return; }
    if (!navigator.geolocation) { return; }
    startLive();
  });
  stopBtn.addEventListener("click", function () {
    stopLive();
    if (HAS_TARGET) { startCountdown(4); }
  });

  if (isAndroid) { androidBtn.hidden = false; }

  if (navigator.permissions && navigator.permissions.query) {
    try {
      navigator.permissions.query({ name: "geolocation" }).then(function (status) {
        permState = status.state;
        if (status.state === "denied") {
          permNote.textContent = "এই সাইটের জন্য লোকেশন আগেই ব্লক করা আছে। "
            + "আড্রেস বারের সেটিংস থেকে Allow না করলে ব্রাউজার নতুন করে চাইবে না।";
        } else if (status.state === "granted") {
          permNote.textContent = "আপনি আগেই এই সাইটে লোকেশনের অনুমতি দিয়েছেন।";
        }
        status.onchange = function () { permState = status.state; };
      }).catch(function () { /* permissions API not available */ });
    } catch (e) { /* ignore */ }
  }

  if (!window.isSecureContext) {
    showError("⚠ এই পেজটি HTTPS ছাড়া খোলা হয়েছে। ব্রাউজার HTTP পেজে লোকেশন দেয় না — "
      + "https:// দিয়ে শুরু হওয়া লিংকটি ব্যবহার করুন।");
  }

  say("");
})();
</script>
</body>
</html>
