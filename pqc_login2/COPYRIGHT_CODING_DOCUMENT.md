# QSafeCloud PQC Authentication System — Copyright Coding Document

**System:** QSafeCloud — Nextcloud Post-Quantum Login with PUF Hardware Key  
**Standard:** NIST FIPS 203 (ML-KEM-512 / CRYSTALS-Kyber)  
**Components:** Login, Register, Recovery, Auto-Logout, ESP32 Hardware Key Firmware  

---

## CATEGORY 1: POST-QUANTUM CRYPTOGRAPHY (ML-KEM-512) CORE ENGINE

### 1.1 ML-KEM-512 Key Encapsulation (Server-Side)
**File:** [MLKEMManager.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Util/MLKEMManager.php#L63-L125)  
**Lines:** 63–125  
**Description:** Core server-side ML-KEM-512 encapsulation engine using OpenSSL KEM primitives. Converts raw PQCMicro public keys (800 bytes) to DER SPKI format, performs real quantum-safe key encapsulation, and outputs ciphertext (768 bytes) + shared secret (32 bytes).

```php
public function encapsulate(string $publicKeyB64): array {
    $normalized = $this->normalizePublicKeyB64($publicKeyB64);
    $rawPublicKey = base64_decode($normalized, true);
    $tmpBase = tempnam(sys_get_temp_dir(), 'pqc_mlkem_');
    $pubFile = $tmpBase . '.pub.der';
    $ctFile = $tmpBase . '.ct.bin';
    $ssFile = $tmpBase . '.ss.bin';
    try {
        file_put_contents($pubFile, $this->derFromRawPublicKey($rawPublicKey));
        $cmd = sprintf(
            '%s pkeyutl -encap -inkey %s -pubin -keyform DER -secret %s -out %s 2>&1',
            $this->opensslCommand() ?? 'openssl',
            escapeshellarg($pubFile),
            escapeshellarg($ssFile),
            escapeshellarg($ctFile)
        );
        exec($cmd, $output, $code);
        $ciphertext = file_get_contents($ctFile);
        $sharedSecret = file_get_contents($ssFile);
        return [
            'ciphertext_b64' => base64_encode($ciphertext),
            'shared_secret_hex' => bin2hex($sharedSecret),
            'ciphertext_bytes' => self::CIPHERTEXT_BYTES,
            'shared_secret_bytes' => self::SHARED_SECRET_BYTES,
        ];
    } finally {
        @unlink($tmpBase); @unlink($pubFile); @unlink($ctFile); @unlink($ssFile);
    }
}
```

### 1.2 ML-KEM-512 Challenge-Bound Proof Computation
**File:** [MLKEMManager.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Util/MLKEMManager.php#L127-L133)  
**Lines:** 127–133  
**Description:** Server-side proof derivation formula: `SHA-256(shared_secret || challenge || username || device_id)`. Matches the ESP32 hardware proof exactly.

```php
public function computeProof(string $sharedSecretHex, string $challenge, string $username, string $deviceId): string {
    $sharedSecret = hex2bin($sharedSecretHex);
    return hash('sha256', $sharedSecret . $challenge . strtolower($username) . strtolower($deviceId));
}
```

### 1.3 ML-KEM-512 Decapsulation + Proof (Hardware Key Side)
**File:** [arduino.ino](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/arduino.ino#L121-L161)  
**Lines:** 121–161  
**Description:** ESP32 firmware receives Base64 ciphertext over serial, decodes it to 768 bytes, performs ML-KEM-512 decapsulation via PQCKyber, derives shared secret, then computes the same challenge-bound proof formula. **The shared secret never leaves the device.**

```cpp
void processMlkemLogin(String payload) {
    int first = payload.indexOf(':');
    int second = payload.indexOf(':', first + 1);
    String ciphertextB64 = payload.substring(0, first);
    String challenge = payload.substring(first + 1, second);
    String username = payload.substring(second + 1);

    uint8_t ciphertext[MLKEM_CIPHERTEXT_BYTES];
    size_t ciphertextLen = 0;
    base64DecodeToBuffer(ciphertextB64, ciphertext, sizeof(ciphertext), &ciphertextLen);

    if (!mlkem_ready || !kyber.decapsulate(ciphertext) || !kyber.hasSharedSecret()) {
        Serial.println("MLKEM_ERROR:Decapsulation failed");
        return;
    }

    uint8_t proof[32];
    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, kyber.getSharedSecret(), MLKEM_SHARED_SECRET_BYTES);
    mbedtls_md_update(&ctx, (const uint8_t *)challenge.c_str(), challenge.length());
    mbedtls_md_update(&ctx, (const uint8_t *)username.c_str(), username.length());
    mbedtls_md_update(&ctx, (const uint8_t *)hardware_id_hex.c_str(), hardware_id_hex.length());
    mbedtls_md_finish(&ctx, proof);
    mbedtls_md_free(&ctx);

    Serial.print("DEVICE_ID:");
    Serial.println(hardware_id_hex);
    Serial.print("MLKEM_PROOF:");
    Serial.println(bytesToHex(proof, sizeof(proof)));
}
```

---

## CATEGORY 2: PUF-BASED HARDWARE DEVICE IDENTITY

### 2.1 PUF Hardware Root Key Generation (ESP32 Efuse MAC)
**File:** [arduino.ino](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/arduino.ino#L59-L74)  
**Lines:** 59–74  
**Description:** Derives a 256-bit hardware root key from the ESP32 eFuse MAC address via SHA-256. Creates the Physically Unclonable Function (PUF) identity that binds each physical device to a unique 64-hex ID.

```cpp
void generateLegacyHardwareKey() {
    uint64_t chipId = ESP.getEfuseMac();
    uint8_t seed[8];
    for (int i = 0; i < 8; i++) {
        seed[i] = (uint8_t)(chipId >> (i * 8));
    }
    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, seed, 8);
    mbedtls_md_finish(&ctx, hardware_root_key);
    mbedtls_md_free(&ctx);
    hardware_id_hex = bytesToHex(hardware_root_key, 32);
}
```

### 2.2 Device ID Derived from ML-KEM Public Key (SHA-256 Fingerprint)
**File:** [arduino.ino](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/arduino.ino#L76-L84)  
**Lines:** 76–84  
**Description:** For ML-KEM mode, the device ID is a SHA-256 fingerprint of the 800-byte ML-KEM public key. Ensures device identity is cryptographically bound to the PQC keypair.

```cpp
String computeDeviceIdFromMlkemPublicKey() {
    uint8_t digest[32];
    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, kyber.getPublicKey(), MLKEM_PUBLIC_KEY_RAW_BYTES);
    mbedtls_md_finish(&ctx, digest);
    mbedtls_md_free(&ctx);
    return bytesToHex(digest, 32);
}
```

### 2.3 ML-KEM Keypair Persistence in ESP32 Flash
**File:** [arduino.ino](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/arduino.ino#L86-L104)  
**Lines:** 86–104  
**Description:** Loads or generates the ML-KEM keypair and persists to non-volatile ESP32 flash under the label `pqc_login_mlkem`. Secret key never leaves the device.

```cpp
void loadOrCreateMlkemKeypair() {
#if defined(ESP32) || defined(ESP_PLATFORM)
    mlkem_ready = kyber.loadKeysFromFlash(MLKEM_FLASH_LABEL);
#endif
    if (!mlkem_ready) {
        Serial.println("MLKEM_KEYGEN_START");
        mlkem_ready = kyber.generateKeys();
#if defined(ESP32) || defined(ESP_PLATFORM)
        if (mlkem_ready) {
            kyber.saveKeysToFlash(MLKEM_FLASH_LABEL);
        }
#endif
    }
    if (mlkem_ready && kyber.hasPublicKey() && kyber.hasSecretKey()) {
        hardware_id_hex = computeDeviceIdFromMlkemPublicKey();
    }
}
```

### 2.4 Server-Side Device Binding Storage (Multi-Device)
**File:** [RegistrationController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/RegistrationController.php#L86-L151)  
**Lines:** 86–151  
**Description:** Manages up to 2 bound Key devices per user (primary + backup). Stores Device ID (64 hex), ML-KEM public key (Base64), label, and binding timestamp. Migrates legacy single-device storage to multi-device JSON format.

```php
private function getDeviceBindings(string $user): array {
    $json = $this->config->getUserValue($user, 'pqc_login', 'device_bindings_json', '');
    $bindings = json_decode($json, true) ?: [];
    $legacyDeviceId = strtolower(trim($this->config->getUserValue($user, 'pqc_login', 'device_id', '')));
    $legacyPublicKey = $this->config->getUserValue($user, 'pqc_login', 'mlkem_public_key_b64', '');
    if ($legacyDeviceId !== '') {
        $found = false;
        foreach ($bindings as $binding) {
            if (($binding['device_id'] ?? '') === $legacyDeviceId) { $found = true; break; }
        }
        if (!$found) {
            array_unshift($bindings, [
                'device_id' => $legacyDeviceId,
                'mlkem_public_key_b64' => $legacyPublicKey,
                'label' => 'Primary Key',
                'bound_at' => time(),
            ]);
        }
    }
    // ... deduplicate + validate 64-hex format
}
```

---

## CATEGORY 3: LOGIN WORKFLOW — CHALLENGE-RESPONSE + PQC VERIFICATION

### 3.1 Login Interceptor — Credential Pre-Verification
**File:** [pqc-login.js](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/js/pqc-login.js#L231-L328)  
**Lines:** 231–328  
**Description:** Intercepts the standard Nextcloud login form submit. Performs email-format enforcement, validates credentials via server-side `check-credentials` endpoint, then triggers the PQC modal before allowing the real login POST.

```javascript
function setupLoginInterceptor(loginBtn) {
    loginBtn.addEventListener("click", async (e) => {
        e.preventDefault();
        const email = normalizeLoginEmail(usernameInput.value);
        const password = passwordInput.value;
        if (!isValidLoginEmail(email)) { alert("Please enter a valid email"); return; }
        const credRes = await fetch(apiUrl('/apps/pqc_login/register'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', requesttoken: requestToken() },
            body: JSON.stringify({ action: 'check-credentials', email, user: email, password }),
        });
        const credResult = await credRes.json();
        if (credResult.status !== 'success') { alert(credResult.message); return; }
        window._pqcCred = { username: credResult.user, email, password };
        showPQCModal();
    });
}
```

### 3.2 Server Challenge Generation (Multi-Device ML-KEM Encapsulation)
**File:** [ChallengeController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/ChallengeController.php#L73-L172)  
**Lines:** 73–172  
**Description:** Generates a per-session random challenge. For each bound ML-KEM device, performs independent encapsulation and caches the per-device shared secrets. Returns all device ciphertexts to the client for forwarding to the correct Key. Checks for frozen accounts before issuing challenges.

```php
public function getChallenge(): DataResponse {
    $challenge = 'COMPETITION_CHALLENGE_' . bin2hex(random_bytes(16));
    $user = strtolower(trim((string)$this->request->getParam('user', '')));
    $_SESSION['pqc_challenge'] = $challenge;
    $bindings = $this->getDeviceBindings($user);
    $mlkemDevices = [];
    $pendingSecrets = [];
    foreach ($bindings as $binding) {
        $publicKeyB64 = $binding['mlkem_public_key_b64'] ?? '';
        $deviceId = strtolower($binding['device_id'] ?? '');
        if ($publicKeyB64 === '') continue;
        $encapsulation = $this->mlkem->encapsulate($publicKeyB64);
        $pendingSecrets[$deviceId] = [
            'shared_secret_hex' => $encapsulation['shared_secret_hex'],
            'ciphertext_b64' => $encapsulation['ciphertext_b64'],
        ];
        $mlkemDevices[] = [
            'device_id' => $deviceId,
            'ciphertext_b64' => $encapsulation['ciphertext_b64'],
        ];
    }
    // Store pending secrets in session + user config, return mode=mlkem512
}
```

### 3.3 Client-Side Web Serial — Challenge/Ciphertext Delivery to Hardware
**File:** [pqc-login.js](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/js/pqc-login.js#L1037-L1192)  
**Lines:** 1037–1192  
**Description:** Opens Web Serial at 115200 baud. For ML-KEM mode: identifies which bound Key is connected via `BIND` handshake, then sends `LOGIN_DECAP:<ciphertext_b64>:<challenge>:<username>\n`. Reads `DEVICE_ID:` + `MLKEM_PROOF:` lines. Falls back to legacy `CHALLENGE:<nonce>\n` → `PQC_SIG:` protocol.

```javascript
async function connectKey() {
    const challengeData = await fetch(apiUrl('/apps/pqc_login/challenge') + '?user=' + encodeURIComponent(username)).then(r => r.json());
    activePort = await navigator.serial.requestPort();
    await activePort.open({ baudRate: 115200 });
    await new Promise(r => setTimeout(r, 1500));

    if (isMlkemMode && mlkemDevices.length > 1) {
        const connectedDeviceId = await identifyConnectedEsp32(activePort);
        const selected = mlkemDevices.find(d => String(d.device_id).toLowerCase() === connectedDeviceId);
        ciphertextB64 = selected.ciphertext_b64;
    }

    const serialCommand = isMlkemMode
        ? `LOGIN_DECAP:${ciphertextB64}:${challenge}:${normalizedUsername}\n`
        : `CHALLENGE:${challenge}\n`;
    await writer.write(encoder.encode(serialCommand));
    // Parse DEVICE_ID + MLKEM_PROOF (or PQC_SIG) from serial buffer...
}
```

### 3.4 Server-Side ML-KEM Proof Verification + Session Login
**File:** [VerifyController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/VerifyController.php#L162-L256)  
**Lines:** 162–256  
**Description:** Loads the cached shared secret for the claimed device, recomputes expected proof via `SHA-256(shared_secret || challenge || user || device_id)`, compares using `hash_equals()` (constant-time), and on success calls `$this->userSession->setUser()` to authenticate the Nextcloud session.

```php
private function verifyMlkem(string $userInput, string $deviceIdInput, string $proof, string $storedDeviceId, string $challenge, string $publicKeyB64): DataResponse {
    $keyMatchesBound = hash_equals($storedDeviceId, $deviceIdInput);
    $devicePending = $pendingByDevice[$storedDeviceId] ?? null;
    $sharedSecretHex = $devicePending['shared_secret_hex'] ?? $_SESSION['pqc_mlkem_shared_secret'] ?? '';
    $expectedProof = $this->mlkem->computeProof($sharedSecretHex, $challenge, $userInput, $storedDeviceId);
    $proofValid = hash_equals($expectedProof, $proof) && $sessionUser === $userInput;

    if (!$keyMatchesBound || !$proofValid) {
        return new DataResponse(['status' => 'fail', 'audit' => $this->buildMlkemAuditPayload(...)], 401);
    }

    $user = $this->userManager->get($userInput);
    $this->userSession->setUser($user);
    $this->userSession->setLoginName($userInput);
    return new DataResponse(['status' => 'ok', 'pqc_verified' => true, 'audit' => $audit, ...]);
}
```

### 3.5 Cryptographic Evidence Audit Receipt + Ed25519 Signing
**File:** [VerifyController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/VerifyController.php#L333-L465)  
**Lines:** 333–465  
**Description:** Builds a detailed audit payload for every verification (success or failure). Canonical JSON is hashed SHA-256 and signed with a persistent server Ed25519 keypair via libsodium. The UI displays this signed evidence report before allowing dashboard entry.

```php
private function buildVerificationReceipt(array $audit): array {
    $evidence = [
        'version' => 'pqc-login-audit-v1',
        'issued_at' => gmdate('c'),
        'mode' => $audit['mode'],
        'algorithm' => $audit['algorithm'],
        'challenge' => $audit['challenge'],
        'device_id' => $audit['hardware_public_key'],
        'proof_valid' => (bool)($audit['signature_valid']),
    ];
    $canonical = $this->canonicalJson($evidence);
    $hash = hash('sha256', $canonical);
    [$publicKeyB64, $secretKeyB64] = $this->signingKeypair();
    $signatureB64 = base64_encode(sodium_crypto_sign_detached($canonical, base64_decode($secretKeyB64)));
    return [
        'receipt_hash_sha256' => $hash,
        'server_signature_b64' => $signatureB64,
        'signature_algorithm' => 'Ed25519 (libsodium)',
    ];
}
```

---

## CATEGORY 4: REGISTRATION WORKFLOW — MULTI-DEVICE BINDING

### 4.1 Multi-Device Registration (Primary + Backup Key)
**File:** [RegistrationController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/RegistrationController.php#L213-L338)  
**Lines:** 213–338  
**Description:** Single endpoint dispatches to `check-credentials`, `kba-question`, `setup-kba`, `recovery-verify`, `recovery-rebind`, `recovery-freeze`, or full registration. On registration: creates Nextcloud user (if new), normalizes ML-KEM public keys from payload, binds up to 2 devices, and initializes Shamir recovery secrets.

```php
public function register(): DataResponse {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';
    if ($action === 'check-credentials') return $this->checkCredentials($data);
    if ($action === 'kba-question') return $this->getKbaQuestion($data);
    if ($action === 'setup-kba') return $this->setupKba($data);
    if ($action === 'recovery-verify') return $this->recoveryVerify($data);
    if ($action === 'recovery-rebind') return $this->recoveryRebind($data);
    if ($action === 'recovery-freeze') return $this->recoveryFreeze();

    $devices = $this->normalizeDevicePayloads($data);
    if (!$this->userManager->userExists($user)) {
        $newUser = $this->userManager->createUser($user, $password);
        $newUser->setDisplayName($displayName);
        $newUser->setEMailAddress($email);
    }
    foreach ($devices as $device) {
        $this->storeDeviceBinding($user, $device['device_id'], $device['mlkem_public_key_b64']);
    }
    $sssData = $this->mrsManager->initializeForUser($user);
    return new DataResponse(['status' => 'success', 'sss' => $sssData, ...]);
}
```

### 4.2 ML-KEM Registration Protocol (Browser → Key via Serial)
**File:** [pqc-serial.js](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/js/pqc-serial.js#L98-L165)  
**Lines:** 98–165  
**Description:** Shared binding helper. Sends `REGISTER_MLKEM\n` twice to the Key. Parses `DEVICE_ID:<64hex>` + `MLKEM_PK_B64:<800-byte raw pk as base64>`. Falls back to legacy `BIND\n` → `PQC_ID:<64hex>` protocol for older firmware.

```javascript
async function bindEsp32Device(opts) {
    const port = await navigator.serial.requestPort();
    await port.open({ baudRate: 115200 });
    await new Promise(r => setTimeout(r, bootDelayMs));

    onStatus('Requesting ML-KEM public key...');
    await writeLine(port, 'REGISTER_MLKEM');
    await new Promise(r => setTimeout(r, 300));
    await writeLine(port, 'REGISTER_MLKEM');

    let result = await readUntil(
        port,
        (parsed) => parsed.key.length === 64 && parsed.mlkemPublicKeyB64.length > 1000,
        3000
    );

    if (result.parsed.key && result.parsed.mlkemPublicKeyB64) {
        return {
            key: result.parsed.key,
            mlkemPublicKeyB64: result.parsed.mlkemPublicKeyB64,
            mode: 'mlkem512',
        };
    }

    // Fallback to legacy BIND
    await writeLine(port, 'BIND');
    result = await readUntil(port, (parsed) => parsed.key.length === 64, timeoutMs);
    return { key: result.parsed.key, mode: 'legacy' };
}
```

### 4.3 Knowledge-Based Authentication (KBA) Storage — Password-Hashed Answers
**File:** [RegistrationController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/RegistrationController.php#L341-L378)  
**Lines:** 341–378  
**Description:** Stores 3 security questions. Each answer is normalized (lowercase + collapse whitespace), then hashed with `password_hash(PASSWORD_DEFAULT)`. Requires 2/3 correct answers during recovery.

```php
private function storeKbaSet(string $user, array $items): void {
    $clean = [];
    foreach ($items as $item) {
        $question = trim($item['question'] ?? '');
        $answer = $this->normalizeKbaAnswer($item['answer'] ?? '');
        $clean[] = [
            'question' => $question,
            'answer_hash' => password_hash($answer, PASSWORD_DEFAULT),
        ];
    }
    $this->config->setUserValue($user, 'pqc_login', 'kba_questions_json', json_encode(array_slice($clean, 0, 3)));
    $this->config->setUserValue($user, 'pqc_login', 'kba_required_correct', '2');
}
```

---

## CATEGORY 5: ACCOUNT RECOVERY — SHAMIR'S SECRET SHARING + FREEZE

### 5.1 Shamir's Secret Sharing (3-of-2 Threshold) over GF(2^8)
**File:** [SSSManager.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Util/SSSManager.php#L1-L105)  
**Lines:** 1–105  
**Description:** Pure-PHP implementation of Shamir's Secret Sharing in Galois Field GF(2^8) with AES irreducible polynomial 0x11d. Splits 32-byte master recovery secret into 3 shares; any 2 shares reconstruct. Share index byte (1–3) is prepended to each binary share.

```php
class SSSManager {
    private static $exp = [];
    private static $log = [];

    private static function init() {
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11d; // AES polynomial
        }
        for ($i = 255; $i < 510; $i++) self::$exp[$i] = self::$exp[$i - 255];
    }

    public function split(string $secretBin, int $n = 3, int $t = 2): array {
        self::init();
        $shares = array_fill(0, $n, '');
        for ($i = 0; $i < $n; $i++) $shares[$i] = chr($i + 1);
        for ($s = 0; $s < strlen($secretBin); $s++) {
            $poly = [ord($secretBin[$s])];
            for ($j = 1; $j < $t; $j++) $poly[] = random_int(0, 255);
            for ($i = 1; $i <= $n; $i++) {
                $val = 0; $x_pow = 1;
                foreach ($poly as $coeff) {
                    $val ^= self::mul($coeff, $x_pow);
                    $x_pow = self::mul($x_pow, $i);
                }
                $shares[$i - 1] .= chr($val);
            }
        }
        return array_map('bin2hex', $shares);
    }

    public function reconstruct(array $sharesHex): string {
        // Lagrange interpolation over GF(2^8) ...
    }
}
```

### 5.2 Master Recovery Secret (MRS) Split + Store — Share A Burned After Use
**File:** [MRSManager.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Util/MRSManager.php#L1-L63)  
**Lines:** 1–63  
**Description:** `initializeForUser` generates 32 random bytes as MRS, splits via SSS into Share A (server-stored), Share B (mnemonic-formatted hex string for user), Share C (binary file for download). `verifyAndBurn` reconstructs MRS from Share A + user-supplied Share, then **destroys** the current Share A by overwriting with `BURNED_<timestamp>` — a one-time-use recovery credential.

```php
class MRSManager {
    public function initializeForUser(string $userId): array {
        $mrs = random_bytes(32);
        $shares = $this->sss->split($mrs, 3, 2);
        $this->config->setUserValue($userId, 'pqc_login', 'pqc_share_a', $shares[0]);
        $this->config->setUserValue($userId, 'pqc_login', 'pqc_mrs_hash', hash('sha256', $mrs));
        $version = (int)$this->config->getUserValue($userId, 'pqc_login', 'pqc_mrs_version', '0');
        $this->config->setUserValue($userId, 'pqc_login', 'pqc_mrs_version', (string)($version + 1));
        return [
            'share_b' => $this->formatShareB($shares[1]),
            'share_c' => $shares[2],
            'version' => $version + 1,
        ];
    }

    public function verifyAndBurn(string $userId, string $userShareHex): ?string {
        $shareA = $this->config->getUserValue($userId, 'pqc_login', 'pqc_share_a', '');
        $expectedHash = $this->config->getUserValue($userId, 'pqc_login', 'pqc_mrs_hash', '');
        $mrs = $this->sss->reconstruct([$shareA, str_replace('-', '', $userShareHex)]);
        if (hash('sha256', $mrs) === $expectedHash) {
            $this->config->setUserValue($userId, 'pqc_login', 'pqc_share_a', 'BURNED_' . time());
            return bin2hex($mrs);
        }
        return null;
    }
}
```

### 5.3 Account Freeze — Disables User + Records Freeze Timestamp
**File:** [RegistrationController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/RegistrationController.php#L591-L620)  
**Lines:** 591–620  
**Description:** Available after successful recovery verification. Calls `setEnabled(false)` on the Nextcloud IUser and writes `account_frozen_at` + `account_frozen_reason=recovery_freeze`. Challenge endpoint checks these values and returns 403 `account_frozen` during login attempts.

```php
private function recoveryFreeze(): DataResponse {
    $user = $this->session->get('pqc_recovery_user');
    $account = $this->userManager->get($user);
    if (method_exists($account, 'setEnabled')) {
        $account->setEnabled(false);
    }
    $this->config->setUserValue($user, 'pqc_login', 'account_frozen_at', (string)time());
    $this->config->setUserValue($user, 'pqc_login', 'account_frozen_reason', 'recovery_freeze');
    $this->session->remove('pqc_recovery_user');
    $this->session->remove('pqc_recovery_mrs');
    return new DataResponse(['status' => 'success']);
}
```

### 5.4 Recovery Rebind — Slot-Aware Device Replacement
**File:** [RegistrationController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/RegistrationController.php#L622-L681)  
**Lines:** 622–681  
**Description:** After MRS + KBA verified, binds a new Key to the `primary` or `backup` slot. Normalizes the new ML-KEM public key → derives its 64-hex device ID → saves bindings (max 2) → rotates the MRS shares (Share A burned, new Share B/C issued).

```php
private function recoveryRebind(array $data): DataResponse {
    $normalizedMlkemPublicKey = $this->mlkem->normalizePublicKeyB64($mlkemPublicKeyB64);
    $pqcKey = $this->mlkem->deviceIdFromPublicKeyB64($normalizedMlkemPublicKey);

    $newBinding = [
        'device_id' => $pqcKey,
        'mlkem_public_key_b64' => $normalizedMlkemPublicKey,
        'label' => $slot === 'backup' ? 'secondary Key' : 'Primary Key',
        'bound_at' => time(),
    ];
    if ($slot === 'primary') {
        $bindingsToSave = array_merge([$newBinding], array_slice($otherBindings, 0, 1));
    } else {
        $bindingsToSave = array_merge(array_slice($otherBindings, 0, 1), [$newBinding]);
    }
    $this->saveDeviceBindings($user, array_slice($bindingsToSave, 0, 2));
    $sssData = $this->mrsManager->initializeForUser($user);
}
```

---

## CATEGORY 6: AUTO-LOGOUT / SESSION SECURITY

### 6.1 Hardware Key Disconnect → Forced Logout
**File:** [pqc-session.js](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/js/pqc-session.js#L1-L76)  
**Lines:** 1–76  
**Description:** Dashboard-side script. Listens to `navigator.serial` `disconnect` event. On trigger: renders a lock-screen overlay, POSTs to `/destroy-session` to terminate the Nextcloud server session, clears `localStorage` / `sessionStorage` / all `caches`, then redirects to login with browser history overwrite.

```javascript
if ('serial' in navigator) {
    navigator.serial.addEventListener('disconnect', async () => {
        const overlay = document.createElement('div');
        overlay.innerHTML = '<div>🔒 PQC Device Removed. Session termination...</div>';
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        try {
            await fetch('/index.php/apps/pqc_login/destroy-session', {
                method: 'POST',
                credentials: 'same-origin'
            });
        } catch (err) {}

        localStorage.clear();
        sessionStorage.clear();
        if ('caches' in window) {
            caches.keys().then(names => names.forEach(n => caches.delete(n)));
        }
        window.history.pushState(null, '', '/index.php/login');
        setTimeout(() => { window.location.href = '/index.php/login'; }, 1800);
    });
}
```

### 6.2 Back-Navigation Guard (Prevent BFCache Replay)
**File:** [pqc-session.js](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/js/pqc-session.js#L66-L75)  
**Lines:** 66–75  
**Description:** Detects when a user navigates back via `pageshow` with `event.persisted === true` or navigation type 2. Forces page reload so stale cached dashboard cannot be viewed after logout.

```javascript
window.addEventListener('pageshow', (event) => {
    if (event.persisted ||
        (window.performance.navigation &&
         window.performance.navigation.type === 2)) {
        console.warn('[PQC] Back navigation blocked');
        window.location.reload();
    }
});
```

### 6.3 Server-Side Session Destruction Endpoint
**File:** [LogoutController.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/lib/Controller/LogoutController.php#L1-L37)  
**Lines:** 1–37  
**Description:** Simple authenticated endpoint that calls Nextcloud's `IUserSession->logout()`, invalidating the session cookie server-side.

```php
public function destroy() {
    $this->userSession->logout();
    return new DataResponse(["status" => "logged_out"]);
}
```

---

## CATEGORY 7: ESP32 HARDWARE KEY FIRMWARE — SERIAL PROTOCOL

### 7.1 Serial Command Dispatcher (Main Loop)
**File:** [arduino.ino](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/arduino.ino#L192-L212)  
**Lines:** 192–212  
**Description:** Main event loop reads newline-terminated serial commands. Dispatches: `REGISTER_MLKEM` → send public key, `LOGIN_DECAP:<ct_b64>:<challenge>:<user>\n` → ML-KEM decap + proof, `BIND` → send legacy device ID, `CHALLENGE:<nonce>\n` → legacy SHA-256 signature response, `PING` → `PONG`.

```cpp
void loop() {
    if (Serial.available()) {
        String input = Serial.readStringUntil('\n');
        input.trim();
        if (input == "REGISTER_MLKEM") {
            processRegisterMlkem();
        } else if (input.startsWith("LOGIN_DECAP:")) {
            processMlkemLogin(input.substring(12));
        } else if (input == "BIND") {
            Serial.print("PQC_ID:");
            Serial.println(hardware_id_hex);
        } else if (input.startsWith("CHALLENGE:")) {
            processLegacyChallenge(input.substring(10));
        } else if (input == "PING") {
            Serial.println("PONG");
        }
    }
    delay(10);
}
```

### 7.2 Setup — PUF Init + ML-KEM Keygen + Boot Banner
**File:** [arduino.ino](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/arduino.ino#L184-L190)  
**Lines:** 184–190  
**Description:** Initializes 115200 baud serial, generates PUF hardware root key from eFuse MAC, loads/generates ML-KEM keypair from flash, then prints readiness banner `PQCMICRO_MLKEM512_READY` or `_ERROR`.

```cpp
void setup() {
    Serial.begin(115200);
    delay(1000);
    generateLegacyHardwareKey();
    loadOrCreateMlkemKeypair();
    Serial.println(mlkem_ready ? "PQCMICRO_MLKEM512_READY" : "PQCMICRO_MLKEM512_ERROR");
}
```

### 7.3 Legacy Challenge-Response (SHA-256 PUF Key + Nonce)
**File:** [arduino.ino](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/arduino.ino#L163-L182)  
**Lines:** 163–182  
**Description:** Backward-compatible protocol for older bindings: `SHA-256(hardware_root_key_32bytes || challenge_string)`. Outputs `PQC_KEY:<device_id>\n` + `PQC_SIG:<64hex>\n`.

```cpp
void processLegacyChallenge(String challenge) {
    uint8_t signature[32];
    mbedtls_md_context_t ctx;
    sha256Start(ctx);
    mbedtls_md_update(&ctx, hardware_root_key, 32);
    mbedtls_md_update(&ctx, (const uint8_t*)challenge.c_str(), challenge.length());
    mbedtls_md_finish(&ctx, signature);
    mbedtls_md_free(&ctx);

    Serial.print("PQC_KEY:");
    Serial.println(hardware_id_hex);
    delay(80);
    Serial.print("PQC_SIG:");
    for (int i = 0; i < 32; i++) printHexByte(signature[i]);
    Serial.println();
}
```

---

## CATEGORY 8: UI TEMPLATES — MULTI-STEP REGISTRATION + RECOVERY FLOWS

### 8.1 Multi-Step Registration Template (Bind → SSS → KBA)
**File:** [register.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/templates/register.php#L1-L168)  
**Lines:** 1–168  
**Description:** Full registration UI with: (1) user/email/password form + dual hardware binding slots (Primary Key / Backup Key buttons with Web Serial), (2) hidden SSS display container with Share B copy, Share C file download, 3 selectable KBA question dropdowns + answer inputs, (3) "I have saved my recovery keys" finalization button.

### 8.2 Four-Step Recovery Template (Verify → Freeze/Rebind → New SSS → Frozen)
**File:** [recovery.php](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/templates/recovery.php#L1-L143)  
**Lines:** 1–143  
**Description:** Step 1: Email + KBA + Share B (text) or Share C (file upload). Step 2: Freeze Account button OR slot-select + new Key binding. Step 3: New Share B copy + new Share C download. Step 4: Account frozen confirmation.

---

## CATEGORY 9: BRANDING INJECTION

### 9.1 Dynamic Branding Replacement (PQCC → QSafe Cloud)
**File:** [pqc-branding.js](file:///var/snap/nextcloud/54257/nextcloud/extra-apps/pqc_login/js/pqc-branding.js#L1-L51)  
**Lines:** 1–51  
**Description:** MutationObserver that continuously replaces `PQCC` tokens with `QSafe Cloud` in window theme object, document title, headings, entity names, and footer info.

---

## COPYRIGHT NOTICE

All code categories above constitute the original copyrightable work of the QSafeCloud PQC Authentication System. The combination of:

- NIST FIPS 203 ML-KEM-512 encapsulation via OpenSSL with PQCMicro raw key translation
- SHA-256 proof formula: `SHA-256(shared_secret || challenge || username || device_id)`
- ESP32 eFuse PUF → hardware root key → device ID derivation
- ML-KEM keypair persistence in ESP32 flash with public-key device fingerprints
- Multi-device (2-key) binding with per-device encapsulation on challenge generation
- One-time-burn Shamir Secret Sharing (3-of-2) master recovery secret lifecycle
- Slot-aware recovery rebind + account freeze mechanism
- Hardware-disconnect auto-logout with session destruction + cache purge
- Ed25519-signed cryptographic evidence audit receipts

is proprietary to the QSafeCloud system.
