#!/usr/bin/env python3
"""
Step 3 - Live Stream View Bypass
ویو فیک در لایو - متفاوته چون:
  - معمولاً WebSocket یا long-poll داره
  - viewer count real-time آپدیت میشه
  - heartbeat/ping باید بره تا count نگه داشته بشه
"""

import requests
import websocket  # pip install websocket-client
import json
import time
import random
import threading
import sys
from typing import Optional

# ===== CONFIG =====
LIVE_CHANNEL = ""   # channel name یا live hash
BASE_URL = "https://www.aparat.com"
# =================

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15",
    "Mozilla/5.0 (Android 14; Mobile; rv:125.0) Gecko/125.0 Firefox/125.0",
]

def find_live_endpoints(channel: str):
    """پیدا کردن endpoint های لایو"""
    print(f"[*] Finding live stream endpoints for: {channel}")

    s = requests.Session()
    s.headers.update({
        "User-Agent": USER_AGENTS[0],
        "Referer": f"{BASE_URL}/{channel}/live",
    })

    # صفحه لایو
    urls_to_check = [
        f"{BASE_URL}/{channel}/live",
        f"{BASE_URL}/live/{channel}",
        f"{BASE_URL}/v/{channel}",
    ]

    import re
    for url in urls_to_check:
        r = s.get(url, timeout=15)
        print(f"  {r.status_code} | {url}")
        if r.status_code == 200:
            # پیدا کردن WebSocket URL
            ws_patterns = [
                r'wss?://[^"\']+',
                r'"socket[^"]*"\s*:\s*"([^"]+)"',
                r'"ws[^"]*"\s*:\s*"([^"]+)"',
            ]
            for pat in ws_patterns:
                for m in re.finditer(pat, r.text):
                    ws_url = m.group(0).strip('"\'')
                    print(f"  [+] WebSocket: {ws_url}")

            # پیدا کردن live API
            api_patterns = [
                r'(https?://[^"\']*live[^"\']*)',
                r'(/api/[^"\']*live[^"\']*)',
                r'(/live/[^"\']*)',
            ]
            for pat in api_patterns:
                for m in re.finditer(pat, r.text):
                    ep = m.group(1)
                    if len(ep) < 200:
                        print(f"  [+] Live endpoint: {ep}")

            # viewer count API
            viewer_patterns = [
                r'(https?://[^"\']*viewer[^"\']*)',
                r'(/api/[^"\']*viewer[^"\']*)',
                r'"viewer[^"]*"\s*:\s*"([^"]+)"',
            ]
            for pat in viewer_patterns:
                for m in re.finditer(pat, r.text):
                    ep = m.group(1)
                    print(f"  [+] Viewer endpoint: {ep}")

            return r.text

    return None


class FakeViewer:
    """یه viewer شبیه‌سازی می‌کنه"""

    def __init__(self, viewer_id: int, join_endpoint: str, heartbeat_endpoint: str = None):
        self.id = viewer_id
        self.join_ep = join_endpoint
        self.heartbeat_ep = heartbeat_endpoint
        self.session = requests.Session()
        self.session.headers.update({
            "User-Agent": random.choice(USER_AGENTS),
            "Referer": f"{BASE_URL}/{LIVE_CHANNEL}/live",
            "X-Forwarded-For": f"{random.randint(1,254)}.{random.randint(1,254)}.{random.randint(1,254)}.{random.randint(1,254)}",
        })
        self.running = False

    def join(self):
        try:
            r = self.session.get(BASE_URL + self.join_ep, timeout=10)
            print(f"  [Viewer {self.id}] join: {r.status_code} | {r.text[:100]}")
            return r.status_code == 200
        except Exception as e:
            print(f"  [Viewer {self.id}] join error: {e}")
            return False

    def heartbeat_loop(self, interval: int = 30):
        """هر interval ثانیه یه بار ping میزنه تا viewer count نگه داره"""
        if not self.heartbeat_ep:
            return
        self.running = True
        while self.running:
            try:
                r = self.session.get(BASE_URL + self.heartbeat_ep, timeout=10)
                print(f"  [Viewer {self.id}] heartbeat: {r.status_code}")
            except:
                pass
            time.sleep(interval)

    def stop(self):
        self.running = False


def test_http_long_poll_viewers(join_ep: str, n_viewers: int = 10):
    """
    تست HTTP-based live viewer bypass
    اگه لایو از WebSocket استفاده نکنه
    """
    print(f"\n[*] HTTP Long-Poll Viewer Test - {n_viewers} fake viewers")
    viewers = []
    threads = []

    for i in range(n_viewers):
        v = FakeViewer(i, join_ep)
        if v.join():
            viewers.append(v)
            time.sleep(0.3)

    print(f"[*] {len(viewers)} viewers joined. نگه می‌داریم...")
    print("[*] Ctrl+C بزن تا بره")

    # چک کردن viewer count
    try:
        count_url = f"{BASE_URL}/api/fa/v1/live/viewercount/{LIVE_CHANNEL}"
        s = requests.Session()
        while True:
            r = s.get(count_url, timeout=10)
            print(f"  [count] {r.status_code} | {r.text[:200]}")
            time.sleep(10)
    except KeyboardInterrupt:
        print("\n[*] Stopping...")
        for v in viewers:
            v.stop()


def test_ws_viewer(ws_url: str, n_connections: int = 5):
    """WebSocket fake viewer"""
    print(f"\n[*] WebSocket Viewer Test - {n_connections} connections to {ws_url}")

    connections = []

    def on_open(ws):
        print(f"  [WS] Connected")
        # معمولاً بعد از connect باید یه join message بفرستیم
        join_msg = json.dumps({
            "type": "join",
            "channel": LIVE_CHANNEL,
            "token": "",
        })
        ws.send(join_msg)

    def on_message(ws, message):
        print(f"  [WS] Message: {message[:200]}")

    def on_error(ws, error):
        print(f"  [WS] Error: {error}")

    def on_close(ws, close_status_code, close_msg):
        print(f"  [WS] Closed: {close_status_code}")

    for i in range(n_connections):
        try:
            ws = websocket.WebSocketApp(
                ws_url,
                header={
                    "User-Agent": random.choice(USER_AGENTS),
                    "Origin": BASE_URL,
                    "Referer": f"{BASE_URL}/{LIVE_CHANNEL}/live",
                },
                on_open=on_open,
                on_message=on_message,
                on_error=on_error,
                on_close=on_close,
            )
            t = threading.Thread(target=ws.run_forever)
            t.daemon = True
            t.start()
            connections.append((ws, t))
            time.sleep(0.5)
        except Exception as e:
            print(f"  [WS {i}] Error: {e}")

    print(f"[*] {len(connections)} WS connections open. Ctrl+C to stop")
    try:
        while True:
            time.sleep(5)
    except KeyboardInterrupt:
        for ws, _ in connections:
            ws.close()


if __name__ == "__main__":
    if not LIVE_CHANNEL:
        print("[!] LIVE_CHANNEL رو set کن (channel name یا live hash)")
        print("[!] Example: LIVE_CHANNEL = 'aparat'")
        sys.exit(1)

    # اول endpoint ها رو پیدا کن
    page_content = find_live_endpoints(LIVE_CHANNEL)

    print("\n[*] اطلاعات بالا رو ببین:")
    print("  1. اگه WebSocket URL پیدا کردی: test_ws_viewer(ws_url) رو صدا کن")
    print("  2. اگه HTTP endpoint پیدا کردی: test_http_long_poll_viewers(join_ep) رو صدا کن")
    print("  3. join_ep و heartbeat_ep رو از recon پر کن")
