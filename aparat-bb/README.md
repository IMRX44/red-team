# Aparat Bug Bounty - Fake View Bypass Testing

## ترتیب کار

### 1. یه ویدیو عمومی برای تست انتخاب کن
- ویدیوی خودت یا یه ویدیو عمومی که owner راضیه
- video hash رو از URL بگیر: `aparat.com/v/XXXXX` → hash = `XXXXX`

### 2. Recon
```bash
python3 step1_recon.py <video_hash>
```
نتیجه: endpoint های view پیدا میشه

### 3. View Bypass Test
```bash
# VIEW_ENDPOINT و VIDEO_HASH رو در فایل پر کن
python3 step2_view_bypass.py
```

### 4. Live View Bypass
```bash
# LIVE_CHANNEL رو در فایل پر کن
python3 step3_live_view_bypass.py
```

## نصب dependencies
```bash
pip3 install requests websocket-client
```

## نکات مهم
- هر تست با delay اجرا میشه (aggressive نیست)
- view count واقعی رو مانیتور کن تا ببینی آیا چیزی تغییر می‌کنه
- screenshot بگیر برای report
