#!/usr/bin/env python3
"""
Step 1 - Aparat View Endpoint Recon
هدف: پیدا کردن endpoint هایی که view count رو ثبت می‌کنن
"""

import requests
import json
import re
import sys

SESSION = requests.Session()
SESSION.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36",
    "Accept-Language": "fa,en;q=0.9",
    "Referer": "https://www.aparat.com/",
})

def get_video_info(video_hash: str):
    """اطلاعات اولیه ویدیو + پیدا کردن video ID واقعی"""
    print(f"[*] Getting video info for: {video_hash}")

    # روش ۱ - API رسمی
    url = f"https://www.aparat.com/etc/api/video/videohash/{video_hash}"
    r = SESSION.get(url, timeout=15)
    print(f"    API v1: {r.status_code}")
    if r.status_code == 200:
        data = r.json()
        print(json.dumps(data, indent=2, ensure_ascii=False)[:2000])
        return data

    # روش ۲ - صفحه ویدیو و parse کردن
    url2 = f"https://www.aparat.com/v/{video_hash}"
    r2 = SESSION.get(url2, timeout=15)
    print(f"    Page fetch: {r2.status_code}")

    # پیدا کردن video_uid و video_id از HTML
    patterns = {
        "video_uid":  r'"video_uid"\s*:\s*"([^"]+)"',
        "video_id":   r'"video_id"\s*:\s*(\d+)',
        "fileid":     r'"fileid"\s*:\s*"([^"]+)"',
        "uid":        r'"uid"\s*:\s*"([^"]+)"',
        "videohash":  r'"videohash"\s*:\s*"([^"]+)"',
    }

    found = {}
    for key, pat in patterns.items():
        m = re.search(pat, r2.text)
        if m:
            found[key] = m.group(1)
            print(f"    [+] {key} = {m.group(1)}")

    # پیدا کردن view API endpoint از JS
    view_patterns = [
        r'(https?://[^"\']+view[^"\']*)',
        r'(/api/[^"\']*view[^"\']*)',
        r'(video/[^"\']*view[^"\']*)',
    ]
    for pat in view_patterns:
        for m in re.finditer(pat, r2.text):
            ep = m.group(1)
            if 'view' in ep.lower() and len(ep) < 200:
                print(f"    [+] Potential view endpoint: {ep}")

    return found


def find_js_endpoints(video_hash: str):
    """پیدا کردن endpoint از فایل‌های JS"""
    print(f"\n[*] Scanning JS files for view endpoints...")

    page = SESSION.get(f"https://www.aparat.com/v/{video_hash}", timeout=15)

    # پیدا کردن JS file ها
    js_files = re.findall(r'src="(https?://[^"]+\.js[^"]*)"', page.text)
    js_files += re.findall(r'src="(/[^"]+\.js[^"]*)"', page.text)

    print(f"    Found {len(js_files)} JS files")

    keywords = ['view', 'watch', 'play', 'seen', 'impression']

    for js_url in js_files[:10]:  # اول ۱۰ تا
        if not js_url.startswith('http'):
            js_url = "https://www.aparat.com" + js_url

        try:
            r = SESSION.get(js_url, timeout=10)
            for kw in keywords:
                if kw in r.text.lower():
                    # پیدا کردن context
                    matches = re.finditer(
                        rf'["\']([^"\']*{kw}[^"\']*)["\']',
                        r.text, re.IGNORECASE
                    )
                    for m in matches:
                        ep = m.group(1)
                        if ('/' in ep or 'api' in ep) and len(ep) < 150:
                            print(f"    [+] JS endpoint ({kw}): {ep}")
        except Exception as e:
            pass


def probe_known_endpoints(video_hash: str, video_id: str = ""):
    """تست endpoint های احتمالی که معمولاً در پلتفرم‌های ویدیو وجود دارن"""
    print(f"\n[*] Probing known endpoint patterns...")

    candidates = [
        f"/video/video/addview/videohash/{video_hash}",
        f"/video/video/view/videohash/{video_hash}",
        f"/api/fa/v1/video/video/addview/videohash/{video_hash}",
        f"/api/fa/v1/video/video/view/videohash/{video_hash}",
        f"/etc/api/addview/{video_hash}",
        f"/v/{video_hash}/view",
    ]

    if video_id:
        candidates += [
            f"/video/video/addview/videoid/{video_id}",
            f"/api/fa/v1/video/video/addview/videoid/{video_id}",
        ]

    base = "https://www.aparat.com"
    for path in candidates:
        url = base + path
        try:
            r = SESSION.get(url, timeout=10)
            print(f"    {r.status_code} | {url}")
            if r.status_code not in [404, 301]:
                print(f"         Body: {r.text[:200]}")
        except Exception as e:
            print(f"    ERR | {url} | {e}")


if __name__ == "__main__":
    # یه ویدیو عمومی برای تست بده
    video_hash = sys.argv[1] if len(sys.argv) > 1 else "REPLACE_WITH_VIDEO_HASH"

    if video_hash == "REPLACE_WITH_VIDEO_HASH":
        print("[!] Usage: python3 step1_recon.py <video_hash>")
        print("[!] Example: python3 step1_recon.py xXyYzZ")
        print("[!] Video hash رو از URL ویدیو در aparat بگیر")
        sys.exit(1)

    info = get_video_info(video_hash)
    find_js_endpoints(video_hash)

    vid_id = ""
    if isinstance(info, dict):
        vid_id = str(info.get("video_id", info.get("id", "")))

    probe_known_endpoints(video_hash, vid_id)

    print("\n[*] Recon complete. اطلاعات بالا رو یادداشت کن برای step 2")
