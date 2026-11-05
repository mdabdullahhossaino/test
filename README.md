# hound 🐶
Hound is a simple and light tool for information gathering and capture exact GPS coordinates
![image](https://user-images.githubusercontent.com/42796435/229538253-e0a9c811-60e4-4294-bd3b-8eb7621b51f5.png)

# What is Hound?
<p>Hound is a tool that can remotely capture the exact GPS coordinates of a target device using a PHP server, and can also grab basic information about the system and ISP. This tool can be very helpful in information gathering. you can get following information of the target device</p>
<ul>
  <li>Longitude</li>
  <li>Latitude</li>
  <li>Device Model</li>
  <li>Operating System</li>
  <li>Number of CPU Cores</li>
  <li>Screen Resolution</li>
  <li>User agent</li>
  <li>Public IP Address</li>
  <li>Browser Name</li>
  <li>ISP Information</li>
</ul>

## Features
  <p>The tool offers a wide range of features and functionality, including:</p>
    <ul>
  <li>Capture Exact GPS Location</li>
  <li>Automated Data Collection</li>
   <li>User-friendly Interface</li>
   <li><b>Custom link mode:</b> give Hound any link, get a tracked link that ends on it</li>
   <li>Live terminal report while the target is on the trap page</li>
   <li>IP based location fallback when the target blocks GPS</li>
</ul>

## Custom link mode (v0.3)
In this mode Hound asks for a link before it starts the server. You can paste any
`http://` or `https://` link: a YouTube video, a Google search, an image, a video,
an article, anything.

```
? Paste the link you want to mask:
> https://www.youtube.com/watch?v=IiJRyVmITgI

[+] Custom link mode: tracking, then redirecting to https://www.youtube.com/watch?v=IiJRyVmITgI
[*] Your tracked link:
    https://random-words-1234.trycloudflare.com
```

That tunnel link is the "modified" link. When the target opens it, Hound:

1. logs the public IP, user agent, referer and IP based location (city/ISP) — `ip.php`
2. shows a small "Just a moment…" page that asks the browser for GPS and posts the
   device report to `webhook.php`
3. forwards the visitor to the link you supplied

Everything is printed live in the terminal with the Google Maps link of the captured
coordinates. If the target denies the GPS permission, Hound still shows the IP based
approximate location, so you are not left empty handed.

> **Why a tunnel link and not the original link?** A website only receives the IP and
> browser data of its own visitors, so a tracker cannot be "inserted" into
> `youtube.com/...` itself. Hound works like a tracked short link: it serves a tiny page
> on your own tunnel, collects the data and then redirects to the link you gave. The
> visitor ends up exactly on the link you chose.

Leave the link empty (just press Enter) to keep the old behaviour: the built-in Hound
chat page is served instead.

## Consent mode (`--consent`)

`--consent` swaps the tracked redirect page for `share.php`: a page that says in plain
words what it collects, who receives it, and that nothing is sent unless the visitor
taps **"আমার লোকেশন শেয়ার করুন"**.

```
bash hound.sh --consent -u "https://youtu.be/xxxx"
```

What the page does:

* shows the destination host, the data that will be sent and a `না` option — no hidden
  redirect and no masking of the target link
* asks for location only from a real tap (a user gesture), which is what makes the
  browser permission prompt appear reliably on Android and iOS
* tries a high accuracy fix, then falls back once to a network based fix
* when the phone's Location is off it explains step by step what to enable, and on
  Android offers a button that opens the system Location settings screen
* never touches the OS location switch itself — a web page cannot do that
* sends the report and only then offers **এগিয়ে যান** (auto-continues after 6 seconds)
* offers an optional 1 minute live update (`watchPosition`) that the visitor can stop
* logs no IP address: `ip.php` is skipped in this mode, nothing is collected before
  the visitor consents

| Mode | Entry page | IP log | Location |
|------|-----------|--------|----------|
| `--consent` | `share.php` | no | only after the visitor taps Allow |
| default | `tracker.php` | yes (`ip.php`) | requested automatically on load |

## This Tool Tested On :
<ul>
  <li>Kali Linux</li>
  <li>Windows(WSL)</li>
  <li>Termux</li>
  <li>MacOS</li>
  <li>Ubuntu</li>
  <li>Parrot Sec OS</li>
</ul>

## Installing and requirements
<p>This tool require PHP for webserver, wget & unzip for download and extract cloudflare. First run following command on your terminal</p>

```
apt-get -y install php unzip git wget
```

Make sure `allow_url_fopen` is enabled in PHP (it is by default) if you want the
server side IP geolocation fallback.

## Installing (Kali Linux/Termux):

```
git clone https://github.com/techchipnet/hound
cd hound
bash hound.sh
```

## Usage

```
bash hound.sh                                  # interactive, asks for a link
bash hound.sh -u "https://youtu.be/xxxx"       # start directly with a link
bash hound.sh --consent -u "https://youtu.be/xxxx"   # explicit consent page
bash hound.sh --no-geoip                       # skip the IP geolocation lookup
bash hound.sh -h                               # help
```

Then choose `Y` for a public cloudflared link or `N` for `http://127.0.0.1:8080`
(local, only your own device can open it — useful to test the flow).

### Example session

```
[?] Paste the link you want to mask:
> youtube.com/watch?v=IiJRyVmITgI

[+] Custom link mode: tracking, then redirecting to https://youtube.com/watch?v=IiJRyVmITgI
[?] Do you want to use cloudflared tunnel? [Y/N]: Y
[+] Starting php server...
[+] Starting cloudflared tunnel...
[*] Your tracked link:
    https://soft-moon-1234.trycloudflare.com
[*] Visitors are forwarded to: https://youtube.com/watch?v=IiJRyVmITgI
[*] Waiting for targets, Press Ctrl + C to exit...

[+] Target opened the link!
  IP:           203.0.113.7
  Location:     Dhaka, Dhaka Division, Bangladesh (BD)
  ISP/Org:      Example ISP
  Coordinates:  23.8103, 90.4125
  Map:          https://www.google.com/maps?q=23.8103,90.4125

[+] Live report from target
  Hound - Information Gathering Report
  ...
  GPS Lat Long Information
  Latitude: 23.8103
  Longitude: 90.4125
  Map Location:  https://www.google.com/maps/place/23.8103,90.4125
```

## Files created at runtime

| File | Content |
|------|---------|
| `ip.txt` | raw IP / device hit, consumed by `hound.sh` |
| `data.txt` | every browser report posted to `webhook.php` |
| `saved.ip.txt` | archive of all captured IP entries |
| `targetreport.txt` | previous `data.txt` moved aside on each run |
| `cf.log` | cloudflared log (the tunnel URL lives here) |

## Accuracy notes

* Exact GPS needs the target to tap **Allow** on the browser location prompt. On phones
  this prompt is only shown on HTTPS pages, which is why the cloudflared tunnel is the
  recommended mode.
* If GPS is denied, the location is the IP based estimate: usually accurate to the
  city/ISP level, not the street.
* VPNs, proxies and mobile carrier NAT can move the IP estimate to another city or
  country. `ip-api.com` is used for the lookup (free tier, no key, rate limited).
* The redirect page waits at most 10 seconds for the location prompt, so the target is
  never stuck on it.

## Change log:
Version: 0.3: Custom link mode (paste any URL, get a tracked link), live terminal reports,
IP geolocation fallback, static `index.php` front controller.

Version: 0.2: Remove Ngrok and update cloudflared tunnel

### Video Demo
[![Hound Demo](https://img.youtube.com/vi/IiJRyVmITgI/0.jpg)](https://www.youtube.com/watch?v=IiJRyVmITgI)

### For More Video subcribe <a href="http://youtube.com/techchipnet">TechChip YouTube Channel</a>

## Legal notice
<p>Hound is created to help in penetration testing and security awareness training. Use it
only on devices and people who gave you explicit permission. Capturing somebody's
location, IP or device data without consent is illegal in most countries and is not
condoned by the author. You are responsible for your own actions.</p>
credit - Chatbot template : Masud Rana
