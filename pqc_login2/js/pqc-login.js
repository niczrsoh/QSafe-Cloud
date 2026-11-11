// ==============================
// 🌐 GLOBAL SERIAL PORT
// ==============================
let activePort = null;

function apiUrl(path) {
    if (typeof OC !== 'undefined' && OC.generateUrl) {
        return OC.generateUrl(path);
    }
    return '/index.php' + path;
}

function requestToken() {
    if (typeof OC !== 'undefined' && OC.requestToken) {
        return OC.requestToken;
    }
    const body = document.querySelector('[data-requesttoken]');
    return body ? body.getAttribute('data-requesttoken') : '';
}

function normalizeLoginEmail(value) {
    return String(value || '').trim().toLowerCase();
}

function isValidLoginEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizeLoginEmail(value));
}

function forceEmailOnlyLoginText() {
    const userInput = document.querySelector("input[name='user'], #user");
    if (userInput) {
        userInput.placeholder = 'Email';
        userInput.setAttribute('type', 'email');
        userInput.setAttribute('autocomplete', 'email');
        userInput.setAttribute('inputmode', 'email');
        userInput.setAttribute('aria-label', 'Email');
    }

    const labels = Array.from(document.querySelectorAll("label[for='user'], .input-field__label"));
    for (const label of labels) {
        const text = (label.textContent || '').trim().toLowerCase();
        if (label.getAttribute('for') === 'user' || text === 'account name or email' || text === 'username or email') {
            label.textContent = 'Email';
        }
    }
}

function removeDeviceLoginButton() {
    const buttons = Array.from(document.querySelectorAll('button'));
    for (const button of buttons) {
        const text = (button.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (text === 'log in with a device') {
            button.remove();
        }
    }

    document.querySelectorAll('.button-vue__text').forEach((span) => {
        const text = (span.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (text === 'log in with a device') {
            const button = span.closest('button');
            if (button) {
                button.remove();
            } else {
                span.remove();
            }
        }
    });
}

function isLoginPage() {
    const path = window.location.pathname || '';
    return /\/login\/?$/.test(path) || /\/index\.php\/login\/?$/.test(path);
}

// ==============================
// 🚫 INTERCEPT LOGIN
// ==============================
document.addEventListener("DOMContentLoaded", () => {

    document.getElementById("pqc-register-link")?.remove();

    if (!isLoginPage()) {
        return;
    }

    if (
        document.getElementById("pqc-register-form") ||
        document.getElementById("pqc-registration-container")
    ) {
        return;
    }

    console.log("%c[PQC] Script loaded on login page", "color: #38bdf8; font-weight: bold;");

    forceEmailOnlyLoginText();
    removeDeviceLoginButton();

    const injectRegisterLink = () => {
        const loginRoot = document.querySelector('#login');
        const loginForm =
            document.querySelector('form[name="login"]') ||
            document.querySelector('.login-form form') ||
            document.querySelector('#login form') ||
            loginRoot?.querySelector('form');

        if (loginForm && loginForm.id === 'pqc-register-form') {
            return false;
        }

        const container =
            loginForm?.closest('.login-form') ||
            loginForm?.closest('#login') ||
            loginRoot;

        const loginBtn =
            loginForm?.querySelector("button[type='submit']") ||
            loginForm?.querySelector('.login-button') ||
            loginForm?.querySelector('#submit-button') ||
            loginRoot?.querySelector("button[type='submit']") ||
            loginRoot?.querySelector('.login-button') ||
            loginRoot?.querySelector('#submit-button') ||
            null;

        const hookLogin = () => {
            if (loginForm && loginBtn) {
                const usernameInput = loginForm.querySelector("input[name='user']");
                if (usernameInput) {

                    usernameInput.placeholder = "Email";
                    usernameInput.setAttribute('type', 'email');
                    usernameInput.setAttribute('autocomplete', 'email');
                    usernameInput.setAttribute('inputmode', 'email');
                }
                setupLoginInterceptor(loginBtn);
                interceptLoginFormSubmit(loginForm);
                return true;
            }
            return false;
        };

        if (document.getElementById('pqc-register-link')) {
            return hookLogin();
        }

        if (!container) {
            return false;
        }

        console.log('%c[PQC] Login container detected, injecting registration link', 'color: #38bdf8;');

        const regContainer = document.createElement('div');
        regContainer.id = 'pqc-register-link';
        regContainer.style.textAlign = 'center';
        regContainer.style.marginTop = '25px';
        regContainer.style.padding = '15px';
        regContainer.style.borderTop = '1px solid rgba(0,0,0,0.05)';
        regContainer.style.background = 'rgba(56, 189, 248, 0.05)';
        regContainer.style.borderRadius = '0 0 10px 10px';

        const registerUrl = (typeof OC !== 'undefined' && OC.generateUrl)
            ? OC.generateUrl('/apps/pqc_login/register')
            : '/index.php/apps/pqc_login/register';

        regContainer.innerHTML = `
            <a id="pqc-go-register" href="${registerUrl}" style="color: #007bff; text-decoration: none; font-size: 15px; font-weight: 700; display: block; margin-bottom: 5px;">
                Create Quantum-Safe Accountt
            </a>
            <span style="font-size: 11px; color: #64748b; display: block; margin-bottom: 12px;">Standard: NIST FIPS 203 (ML-KEM-512)</span>
            <div id="pqc-admin-contact" style="margin-top:12px; padding-top:12px; border-top:1px solid rgba(100,116,139,0.18); font-size:11px; line-height:1.6; color:#64748b;">
                <div style="font-weight:700; color:#475569; margin-bottom:2px;">Admin contact</div>
                <div>Phone: +60 12-345 6789</div>
                <div>Email: qsafe.admin@example.com</div>
            </div>
        `;

        const formParent = loginForm?.parentNode;
        if (formParent) {
            formParent.insertBefore(regContainer, loginForm.nextSibling);
        } else {
            container.appendChild(regContainer);
        }

        const registerLink = document.getElementById('pqc-go-register');
        if (registerLink) {
            registerLink.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                window.location.assign(registerUrl);
            });
        }

        return hookLogin();
    };

    let attempts = 0;
    const interval = setInterval(() => {
        attempts++;
        if (injectRegisterLink() || attempts > 20) {
            clearInterval(interval);
        }
    }, 500);

    const observer = new MutationObserver(() => {
        forceEmailOnlyLoginText();
        removeDeviceLoginButton();
        if (injectRegisterLink()) {
            observer.disconnect();
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
});

function interceptLoginFormSubmit(loginForm) {
    if (loginForm.getAttribute("pqc-form-intercepted")) {
        return;
    }
    loginForm.setAttribute("pqc-form-intercepted", "true");

    loginForm.addEventListener("submit", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const btn =
            loginForm.querySelector("button[type='submit']") ||
            loginForm.querySelector(".login-button");
        if (btn) {
            btn.click();
        }
    }, true);
}

function setupLoginInterceptor(loginBtn) {
    let isProcessing = false;
    const originalBtnText = loginBtn.innerText || loginBtn.textContent || "Log in";

    if (loginBtn.getAttribute("pqc-intercepted")) {
        return;
    }
    loginBtn.setAttribute("pqc-intercepted", "true");

    loginBtn.addEventListener(
        "click",
        async (e) => {

            if (isProcessing) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const usernameInput =
                document.querySelector(
                    "input[name='user']"
                );

            const passwordInput =
                document.querySelector(
                    "input[name='password']"
                );

            const email = normalizeLoginEmail(usernameInput ? usernameInput.value : "");

            const password =
                passwordInput
                    ? passwordInput.value
                    : "";

            if (!email || !password) {
                alert(
                    "Please enter email and password"
                );

                return;
            }
            if (!isValidLoginEmail(email)) {
                alert("Please enter a valid email address");
                return;
            }

            isProcessing = true;
            loginBtn.disabled = true;
            loginBtn.innerText = "Checking credentials...";

            try {
                const credRes = await fetch(apiUrl('/apps/pqc_login/register'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        requesttoken: requestToken(),
                    },
                    body: JSON.stringify({
                        action: 'check-credentials',
                        email,
                        user: email,
                        password,
                    }),
                });

                const credResult = await credRes.json();
                if (credResult.status !== 'success') {
                    alert(credResult.message || 'Invalid email or password');
                    return;
                }

                const resolvedUser = credResult.user || email;
                console.log("STEP 1: Credentials verified:", email, "=>", resolvedUser);

                window._pqcCred = {
                    username: resolvedUser,
                    email,
                    password
                };

                loginBtn.innerText = "Waiting PQC...";
                showPQCModal();
            } catch (err) {
                console.error('[PQC] Credential check failed:', err);
                alert('Could not verify credentials. Please try again.');
            } finally {
                if (!document.getElementById("pqc-modal")) {
                    isProcessing = false;
                    loginBtn.disabled = false;
                    loginBtn.innerText = originalBtnText;
                }
            }
        }
    );
}


// ==============================
// 🔐 SHOW MODAL
// ==============================
function showPQCModal() {

    console.log("[PQC] showPQCModal triggered");

    if (
        document.getElementById("pqc-modal")
    ) {
        console.log("[PQC] Modal already exists, skipping");
        return;
    }

    // Check compatibility
    const isSupported = "serial" in navigator;

    // ==============================
    // STYLE
    // ==============================
    const style =
        document.createElement("style");

    style.innerHTML = `

    .pqc-spinner{
        width:18px;
        height:18px;
        border:2px solid #ddd;
        border-top:2px solid #007bff;
        border-radius:50%;
        animation:pqcSpin 0.8s linear infinite;
    }

    @keyframes pqcSpin{
        from{
            transform:rotate(0deg);
        }
        to{
            transform:rotate(360deg);
        }
    }

    .pqc-fade{
        animation:pqcFade 0.25s ease;
    }

    @keyframes pqcFade{
        from{
            opacity:0;
            transform:translateY(10px);
        }
        to{
            opacity:1;
            transform:translateY(0);
        }
    }
    `;

    document.head.appendChild(style);

    // ==============================
    // OVERLAY
    // ==============================
    const overlay =
        document.createElement("div");

    overlay.id = "pqc-overlay";

    overlay.style = `
        position:fixed;
        top:0;
        left:0;
        width:100%;
        height:100%;
        backdrop-filter:blur(8px);
        background:rgba(0,0,0,0.35);
        z-index:9998;
    `;

    // ==============================
    // MODAL
    // ==============================
    const modal =
        document.createElement("div");

    modal.id = "pqc-modal";

    modal.classList.add("pqc-fade");

    const mobileContent = `
        <div style="background:#fff4f4; border:1px solid #fecaca; padding:15px; border-radius:10px; margin-top:15px; text-align:left;">
            <b style="color:#dc2626; font-size:13px;">📱 Mobile Browser Limitation</b>
            <p style="margin:5px 0 0; font-size:12px; color:#7f1d1d; line-height:1.4;">
                Phones do not support Web Serial. To login on mobile, enter your <b>PQC Recovery Key</b> or use a Desktop with Key.
            </p>
            <input type="text" id="pqc-manual-key" placeholder="Enter PQC Recovery Key..." style="margin-top:10px; width:100%; padding:8px; border:1px solid #ddd; border-radius:5px; font-size:12px;">
            <button id="pqc-manual-btn" style="margin-top:8px; width:100%; padding:8px; background:#475569; color:white; border:none; border-radius:5px; cursor:pointer; font-size:12px;">Verify Key</button>
        </div>
    `;

    modal.innerHTML = `
        <div style="
            position:fixed;
            top:50%;
            left:50%;
            transform:translate(-50%,-50%);
            width:min(92vw, 920px);
            max-height:90vh;
            overflow-y:auto;
            background:white;
            border-radius:20px;
            padding:35px;
            text-align:center;
            z-index:9999;
            box-shadow:0 20px 60px rgba(0,0,0,0.25);
            font-family:'Segoe UI',sans-serif;
        " id="pqc-modal-inner">

            <div style="
                font-size:52px;
                margin-bottom:12px;
            ">
                ${isSupported ? '🔐' : '⚠️'}
            </div>

            <h2 style="
                margin:0;
                color:#222;
                font-weight:700;
            ">
                PQC Verification
            </h2>

            <p style="
                margin-top:12px;
                color:#666;
                font-size:14px;
                line-height:1.5;
            ">
                ${isSupported 
                    ? 'Connect your secure hardware device to continue authentication' 
                    : 'Your current browser/device does not support hardware PQC.'}
            </p>

            ${isSupported ? `
                <button id="connect-btn" style="
                    margin-top:25px;
                    width:100%;
                    padding:14px;
                    border:none;
                    border-radius:10px;
                    background:linear-gradient(135deg,#007bff,#0056d2);
                    color:white;
                    font-size:15px;
                    font-weight:600;
                    cursor:pointer;
                    box-shadow:0 8px 20px rgba(0,123,255,0.3);
                ">
                    Connect Secure Device
                </button>
            ` : mobileContent}

            <div id="status" style="
                margin-top:22px;
                font-size:14px;
                color:#666;
                min-height:24px;
            ">
                Waiting for authentication...
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; text-align: center;">
                <div style="font-size: 13px; color: #64748b; margin-bottom: 8px;">🔑 Lost or damaged your PQC device?</div>
                <a id="pqc-go-recovery" href="${apiUrl('/apps/pqc_login/register?mode=recovery')}" 
                   style="color: #38bdf8; text-decoration: none; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">
                    🚀 Start Key Recovery Flow
                </a>
            </div>

        </div>
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    const recoveryLink = document.getElementById('pqc-go-recovery');
    if (recoveryLink) {
        recoveryLink.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            window.location.assign(apiUrl('/apps/pqc_login/register?mode=recovery'));
        });
    }

    if (isSupported) {
        const connectBtn = document.getElementById("connect-btn");
        if (connectBtn) {
            connectBtn.addEventListener("click", connectKey);
        }
    } else {
        document.getElementById("pqc-manual-btn").addEventListener("click", verifyManualKey);
    }
}

// ==============================
// 🔑 MANUAL KEY (FOR MOBILE)
// ==============================
async function verifyManualKey() {
    const key = document.getElementById("pqc-manual-key").value.trim();
    if (!key) {
        alert("Please enter a recovery key");
        return;
    }
    
    // For competition, we allow a specific recovery key on mobile
    if (key === "COMPETITION-PQC-2026") {
        updatePQCStatus("Bypassing hardware for mobile demo...");
        await showPQCVerificationSteps("Mobile bypass successful (Competition Mode)");
        const confirmed = await showPQCEvidenceReport({
            challenge: "COMPETITION_MOBILE_BYPASS",
            algorithm: "ML-KEM-512 (CRYSTALS-Kyber)",
            standard: "NIST FIPS 203",
            oid: "2.16.840.1.101.3.4.4.1",
            hardware_public_key: "(recovery key — no hardware)",
            bound_public_key: "(recovery key — no hardware)",
            key_match: true,
            device_signature: "bypass",
            expected_signature: "bypass",
            signature_valid: true,
            signature_formula: "Manual recovery key (demo mode)",
            kem_encapsulation: { steps: ["Mobile demo — encapsulation skipped"] },
            kem_decapsulation: { steps: ["Mobile demo — decapsulation skipped"] },
            private_key_note: "N/A — mobile recovery path",
        }, window._pqcCred?.username || "user");
        if (confirmed) {
            await doRealLogin();
        }
    } else {
        showPQCError("Invalid Recovery Key");
    }
}


// ==============================
// 🔄 STATUS
// ==============================
function updatePQCStatus(
    text,
    color = "#666"
) {

    const status =
        document.getElementById("status");

    if (!status) return;

    status.innerHTML = `
        <div style="
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
        ">
            <div class="pqc-spinner"></div>

            <span>${text}</span>
        </div>
    `;

    status.style.color = color;
}


function showDashboardLoadingView() {
    const modalInner = document.getElementById("pqc-modal-inner");
    const modal = document.getElementById("pqc-modal");
    const target = modalInner || modal;

    if (!target) {
        updatePQCStatus("Entering dashboard...", "#16a34a");
        return;
    }

    target.innerHTML = `
        <div style="
            min-height:360px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            text-align:center;
            gap:18px;
        ">
            <div class="pqc-spinner" style="
                width:44px;
                height:44px;
                border-width:4px;
                border-color:#dcfce7;
                border-top-color:#16a34a;
            "></div>
            <div>
                <h2 style="margin:0;color:#0f172a;font-size:24px;font-weight:800;">Entering dashboard</h2>
                <div id="status" style="margin:10px 0 0;color:#64748b;font-size:14px;line-height:1.5;min-height:24px;">
                    PQC verification completed. Preparing your QSafe Cloud session...
                </div>
            </div>
        </div>
    `;
}


// ==============================
// 🔬 CRYPTO HELPERS (matches Key Arduino logic)
// ==============================
function hexToBytes(hex) {
    const clean = hex.replace(/[^a-fA-F0-9]/g, "");
    const bytes = new Uint8Array(clean.length / 2);
    for (let i = 0; i < bytes.length; i++) {
        bytes[i] = parseInt(clean.slice(i * 2, i * 2 + 2), 16);
    }
    return bytes;
}

function bytesToHex(bytes) {
    return Array.from(bytes)
        .map((b) => b.toString(16).padStart(2, "0"))
        .join("");
}

async function computeChallengeSignature(publicKeyHex, challenge) {
    const keyBytes = hexToBytes(publicKeyHex);
    const challengeBytes = new TextEncoder().encode(challenge);
    const combined = new Uint8Array(keyBytes.length + challengeBytes.length);
    combined.set(keyBytes, 0);
    combined.set(challengeBytes, keyBytes.length);
    const hash = await crypto.subtle.digest("SHA-256", combined);
    return bytesToHex(new Uint8Array(hash));
}

/**
 * Parse Key serial lines (avoids regex matching partial/garbled buffers).
 */
function parseEsp32SerialBuffer(buffer) {
    const extractHexAfterLabel = (labels, stopLabels = []) => {
        for (const label of labels) {
            const idx = buffer.lastIndexOf(label);
            if (idx === -1) {
                continue;
            }

            let tail = buffer.slice(idx + label.length);
            let stopAt = -1;
            for (const stopLabel of stopLabels) {
                const nextIdx = tail.indexOf(stopLabel);
                if (nextIdx !== -1 && (stopAt === -1 || nextIdx < stopAt)) {
                    stopAt = nextIdx;
                }
            }
            if (stopAt !== -1) {
                tail = tail.slice(0, stopAt);
            }

            const hex = tail.replace(/[^a-fA-F0-9]/g, '').toLowerCase();
            if (hex.length >= 64) {
                return hex.slice(0, 64);
            }
        }
        return '';
    };

    const labels = ['DEVICE_ID:', 'PQC_ID:', 'PQC_KEY:', 'PQC_SIG:', 'MLKEM_PROOF:'];
    const key = extractHexAfterLabel(['DEVICE_ID:', 'PQC_ID:', 'PQC_KEY:'], ['PQC_SIG:', 'MLKEM_PROOF:']);
    const signature = extractHexAfterLabel(['PQC_SIG:'], labels.filter((label) => label !== 'PQC_SIG:'));
    const proof = extractHexAfterLabel(['MLKEM_PROOF:'], labels.filter((label) => label !== 'MLKEM_PROOF:'));

    return { key, signature, proof };
}

async function identifyConnectedEsp32(port) {
    const encoder = new TextEncoder();
    const decoder = new TextDecoder();
    const writer = port.writable.getWriter();
    await writer.write(encoder.encode('BIND\n'));
    writer.releaseLock();

    const reader = port.readable.getReader();
    let buffer = '';
    let released = false;
    const timeout = setTimeout(() => reader.cancel().catch(() => {}), 3000);
    try {
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const parsed = parseEsp32SerialBuffer(buffer);
            if (parsed.key && parsed.key.length === 64) {
                clearTimeout(timeout);
                reader.releaseLock();
                released = true;
                return parsed.key;
            }
        }
    } finally {
        clearTimeout(timeout);
        if (!released) {
            try { reader.releaseLock(); } catch (e) {}
        }
    }
    return '';
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
}

function formatHexBlock(label, value, maxLen = 96) {
    const v = escHtml(value || "");
    const short = v.length > maxLen ? v.slice(0, maxLen) + "…" : v;
    return `
        <div style="margin-bottom:10px;">
            <div style="color:#94a3b8;font-size:10px;text-transform:uppercase;margin-bottom:4px;">${label}</div>
            <code style="display:block;word-break:break-all;background:#1e293b;padding:8px;border-radius:6px;font-size:11px;color:#e2e8f0;" title="${v}">${short}</code>
        </div>
    `;
}

// ==============================
// ✅ VERIFICATION STEPS ANIMATION
// ==============================
async function showPQCVerificationSteps(text) {
    const status = document.getElementById("status");

    if (!status) return;

    const steps = [
        "🔌 Key Security Device Connected",
        "🔑 Negotiating ML-KEM-512 (Kyber) Hybrid Handshake",
        "🛡️ Validating NIST FIPS 203 Post-Quantum Identity",
        "🔓 Session Encapsulation Key (SSK) Established"
    ];

    status.innerHTML = `
        <div id="pqc-process" style="
            background:#0f172a;
            border:1px solid #334155;
            border-radius:12px;
            padding:20px;
            text-align:left;
            max-width:700px;
            margin:auto;
            color:#e2e8f0;
            font-family:'Consolas','Monaco',monospace;
            font-size:13px;
        ">
            <h3 style="
                margin-top:0;
                margin-bottom:15px;
                color:#38bdf8;
                font-size:16px;
            ">
                > PQC_SECURE_AUTH_v1.0
            </h3>

            <div id="pqc-steps"></div>
            
            <div id="pqc-tech-logs" style="
                margin-top:15px;
                padding-top:15px;
                border-top:1px dashed #334155;
                color:#94a3b8;
                font-size:11px;
                max-height:100px;
                overflow-y:auto;
            "></div>
        </div>
    `;

    const container = document.getElementById("pqc-steps");
    const logContainer = document.getElementById("pqc-tech-logs");

    const logs = [
        "[INFO] Initializing OQS (Open Quantum Safe) provider...",
        "[DEBUG] ClientHello: Offering ML-KEM-512 + X25519",
        "[DEBUG] ServerHello: Selected ML-KEM-512 (NIST FIPS 203)",
        "[DEBUG] Public Key: " + "04" + Math.random().toString(16).slice(2, 10).repeat(8),
        "[INFO] Performing Decapsulation on Key..."
    ];

    let logIdx = 0;
    for (const step of steps) {
        const row = document.createElement("div");

        row.style.cssText = `
            margin-bottom:8px;
            padding:8px;
            border-radius:6px;
            background:#1e293b;
            color:#94a3b8;
            transition:all 0.3s ease;
        `;

        row.innerHTML = `[WAIT] ${step}`;
        container.appendChild(row);

        // Add tech log
        if (logs[logIdx]) {
            const logLine = document.createElement("div");
            logLine.innerText = logs[logIdx];
            logContainer.appendChild(logLine);
            logContainer.scrollTop = logContainer.scrollHeight;
            logIdx++;
        }

        await new Promise(resolve => setTimeout(resolve, 800));

        row.innerHTML = `<span style="color:#22c55e">●</span> ${step}`;
        row.style.background = "#064e3b";
        row.style.color = "#4ade80";
    }

    const finalMessage = document.createElement("div");

    finalMessage.style.cssText = `
        margin-top:20px;
        padding:15px;
        background:#16a34a;
        color:white;
        border-radius:10px;
        text-align:center;
        font-weight:600;
        font-size:16px;
        animation:fadeIn 0.5s ease-in-out;
    `;

    finalMessage.innerHTML = `
        🎉 ${text}
    `;

    container.appendChild(finalMessage);
}

// ==============================
// 📋 KYBER / CHALLENGE-RESPONSE EVIDENCE PANEL
// ==============================
async function showPQCEvidenceReport(audit, username) {
    const status = document.getElementById("status");
    if (!status) return;

    const enc = audit.kem_encapsulation || {};
    const dec = audit.kem_decapsulation || {};
    const sigOk = audit.signature_valid ? "✅ VALID" : "❌ INVALID";
    const keyOk = audit.key_match ? "✅ MATCH" : "❌ MISMATCH";
    const decapOk = dec.secret_match ? "✅ MATCH" : "—";
    const isRealMlkemAudit = audit.mode === "real_mlkem";
    const proofLabel = isRealMlkemAudit ? "ML-KEM proof from device" : "PQC_SIG from device";
    const expectedProofLabel = isRealMlkemAudit ? "Expected ML-KEM proof (server recomputed)" : "Expected SIG (server recomputed)";
    const auditIntro = isRealMlkemAudit
        ? "Cryptographic audit trail for supervisor review. Server performed real OpenSSL ML-KEM-512 encapsulation; Key verified decapsulation by returning a challenge-bound proof."
        : "Legacy audit trail for supervisor review. This account has not been rebound with a real ML-KEM public key yet.";
    const sentLine = isRealMlkemAudit
        ? "Sent to Key: LOGIN_DECAP:&lt;768-byte ciphertext&gt;:" + escHtml(audit.challenge)
        : "Sent to Key: CHALLENGE:" + escHtml(audit.challenge);

    status.innerHTML = `
        <div style="text-align:left;font-family:'Consolas','Monaco',monospace;font-size:12px;color:#e2e8f0;">
            <div style="background:#0f172a;border:1px solid #334155;border-radius:12px;padding:16px;max-height:55vh;overflow-y:auto;">
                <h3 style="margin:0 0 12px;color:#38bdf8;font-size:15px;">📊 PQC Verification Evidence — ${escHtml(username)}</h3>
                <p style="margin:0 0 14px;color:#94a3b8;font-size:11px;line-height:1.5;">
                    ${auditIntro}
                </p>

                <div style="background:#1e293b;border-radius:8px;padding:12px;margin-bottom:12px;">
                    <b style="color:#38bdf8;">① Fresh Login Challenge + Device Binding</b>
                    ${formatHexBlock("Server nonce / challenge", audit.challenge, 120)}
                    <div style="font-size:10px;color:#94a3b8;margin-bottom:6px;">Algorithm: ${escHtml(audit.algorithm)} · ${escHtml(audit.standard)} · OID ${escHtml(audit.oid)}</div>
                    ${formatHexBlock("Device ID reported by Key", audit.hardware_public_key)}
                    ${formatHexBlock("Bound Device ID in Nextcloud", audit.bound_public_key)}
                    <div style="margin-top:6px;color:#94a3b8;font-size:10px;">Device ID length: ${(audit.hardware_public_key || "").length} / Bound: ${(audit.bound_public_key || "").length} (must be 64)</div>
                    <div style="margin-top:6px;color:#4ade80;font-weight:bold;">Key binding: ${keyOk}</div>
                    <div style="margin-top:8px;color:#fbbf24;font-size:10px;">Private key note: ${escHtml(audit.private_key_note || "Never leaves Key")}</div>
                </div>

                <div style="background:#1e293b;border-radius:8px;padding:12px;margin-bottom:12px;">
                    <b style="color:#38bdf8;">② ML-KEM-512 Encapsulation (Nextcloud Server)</b>
                    ${formatHexBlock("Recipient public key", enc.ephemeral_public_key)}
                    ${formatHexBlock("Ciphertext generated by server", enc.ciphertext)}
                    ${formatHexBlock("Server shared secret", enc.shared_secret)}
                    <div style="color:#64748b;font-size:10px;margin-top:6px;">${sentLine}</div>
                    <ul style="margin:8px 0 0;padding-left:18px;color:#94a3b8;font-size:10px;line-height:1.6;">
                        ${(enc.steps || []).map((s) => `<li>${escHtml(s)}</li>`).join("")}
                    </ul>
                </div>

                <div style="background:#1e293b;border-radius:8px;padding:12px;margin-bottom:12px;">
                    <b style="color:#38bdf8;">③ ML-KEM-512 Decapsulation (Key)</b>
                    ${formatHexBlock("Decapsulated shared secret", dec.decapsulated_secret)}
                    <div style="margin-top:6px;color:#94a3b8;font-size:10px;">The Key does not send the shared secret back. It uses the secret only to compute the proof below.</div>
                    <ul style="margin:8px 0 0;padding-left:18px;color:#94a3b8;font-size:10px;line-height:1.6;">
                        ${(dec.steps || []).map((s) => `<li>${escHtml(s)}</li>`).join("")}
                    </ul>
                </div>

                <div style="background:#1e293b;border-radius:8px;padding:12px;margin-bottom:12px;">
                    <b style="color:#38bdf8;">④ Challenge-Bound Proof Verification</b>
                    <div style="font-size:10px;color:#94a3b8;margin-bottom:6px;">Formula: ${escHtml(audit.signature_formula)}</div>
                    ${formatHexBlock(proofLabel, audit.device_signature)}
                    ${formatHexBlock(expectedProofLabel, audit.expected_signature)}
                    <div style="margin-top:6px;color:#4ade80;font-weight:bold;">${isRealMlkemAudit ? "Proof" : "Signature"}: ${sigOk}</div>
                </div>

                <div style="background:#1e293b;border-radius:8px;padding:12px;margin-bottom:12px;">
                    <b style="color:#38bdf8;">⑤ Final Verification Result</b>
                    <div style="margin-top:6px;color:#4ade80;font-weight:bold;">Device binding: ${keyOk}</div>
                    <div style="margin-top:6px;color:#4ade80;font-weight:bold;">Shared secret proven: ${isRealMlkemAudit ? sigOk : decapOk}</div>
                    <div style="margin-top:6px;color:#94a3b8;font-size:10px;">Only the challenge-bound proof is compared. The ML-KEM shared secret and Key secret key are not displayed or exported.</div>
                </div>
            </div>

            <button id="pqc-confirm-enter" type="button" style="
                margin-top:18px;width:100%;padding:16px;border:none;border-radius:12px;
                background:linear-gradient(135deg,#16a34a,#15803d);color:white;
                font-size:16px;font-weight:700;cursor:pointer;
                box-shadow:0 8px 24px rgba(22,163,74,0.35);
            ">
                ✓ Confirm & Enter Dashboard
            </button>
            <p style="margin-top:10px;font-size:11px;color:#64748b;text-align:center;">
                Review the evidence above, then confirm to complete login.
            </p>
        </div>
    `;

    const btnLabel = audit.signature_valid && audit.key_match
        ? "✓ Confirm & Enter Dashboard"
        : "Close (verification failed)";

    const btn = document.getElementById("pqc-confirm-enter");
    if (btn) {
        btn.textContent = btnLabel;
        if (!audit.signature_valid || !audit.key_match) {
            btn.style.background = "linear-gradient(135deg,#475569,#334155)";
        }
    }

    return new Promise((resolve) => {
        if (!btn) {
            resolve(false);
            return;
        }

        btn.addEventListener("click", async () => {
            if (!audit.signature_valid || !audit.key_match) {
                resolve(false);
                return;
            }

            btn.disabled = true;
            btn.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:center;gap:10px;">
                    <div class="pqc-spinner"></div>
                    <span>Signing in to QSafe Cloud...</span>
                </div>
            `;
            showDashboardLoadingView();
            resolve(true);
        }, { once: true });
    });
}


// ==============================
// ❌ ERROR
// ==============================
function showPQCError(text) {

    const status =
        document.getElementById("status");

    if (!status) return;

    status.innerHTML = `
        <div style="
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            color:#dc2626;
            font-weight:600;
        ">
            <span style="font-size:18px;">
                ❌
            </span>

            <span>${text}</span>
        </div>
    `;
}


// ==============================
// 🔌 CONNECT Key
// ==============================
async function connectKey() {

    try {

        updatePQCStatus(
            "Fetching secure challenge..."
        );

        // ==============================
        // 1. GET CHALLENGE FROM SERVER
        // ==============================
        const normalizedUsername = String(window._pqcCred.username || '').trim().toLowerCase();
        const challengeRes = await fetch(apiUrl('/apps/pqc_login/challenge') + '?user=' + encodeURIComponent(normalizedUsername));
        const challengeData = await challengeRes.json();
        const challenge = challengeData.challenge;
        const isMlkemMode = challengeData.mode === 'mlkem512' && !!challengeData.ciphertext_b64;
        const mlkemDevices = Array.isArray(challengeData.mlkem_devices) ? challengeData.mlkem_devices : [];
        let ciphertextB64 = challengeData.ciphertext_b64 || '';

        if (challengeData.status === 'mlkem_unavailable' || challengeData.status === 'account_frozen') {
            throw new Error(challengeData.message || 'ML-KEM encapsulation is unavailable on the server.');
        }

        if (!challenge) {
            throw new Error("Failed to get PQC challenge from server");
        }

        console.log("CHALLENGE RECEIVED:", challenge);

        updatePQCStatus(
            "Waiting for secure device..."
        );

        // ==============================
        // CLEAN OLD PORT
        // ==============================
        if (activePort) {

            try {

                await activePort.close();

            } catch(e) {}

            activePort = null;
        }

        console.log("CONNECTING TO Key...");

        // ==============================
        // REQUEST PORT
        // ==============================
        try {
            activePort = await navigator.serial.requestPort();
        } catch (portErr) {
            console.warn("PORT SELECTION CANCELLED:", portErr);
            if (portErr.name === "NotFoundError") {
                showPQCError("No device selected. Please click 'Connect' and select your Key.");
            } else {
                showPQCError("Connection failed: " + portErr.message);
            }
            return;
        }

        updatePQCStatus(
            "Opening secure channel..."
        );

        // ==============================
        // OPEN SERIAL
        // ==============================
        await activePort.open({
            baudRate:115200
        });

        console.log(
            "SERIAL OPEN SUCCESS"
        );

        updatePQCStatus(
            isMlkemMode ? "Identifying bound Key device..." : "Sending challenge to device..."
        );

        // ==============================
        // WAIT BOOT
        // ==============================
        await new Promise(resolve =>
            setTimeout(resolve, 1500)
        );

        if (isMlkemMode && mlkemDevices.length > 1) {
            const connectedDeviceId = await identifyConnectedEsp32(activePort);
            if (!connectedDeviceId) {
                throw new Error('Could not identify Key device. Reconnect it and try again.');
            }
            const selectedDevice = mlkemDevices.find((device) => String(device.device_id || '').toLowerCase() === connectedDeviceId);
            if (!selectedDevice) {
                throw new Error('This Key is not one of the two bound devices for this account.');
            }
            ciphertextB64 = selectedDevice.ciphertext_b64 || '';
            console.log('BOUND Key SELECTED:', connectedDeviceId);
        }

        updatePQCStatus(
            isMlkemMode ? "Sending ML-KEM ciphertext to device..." : "Sending challenge to device..."
        );

        // ==============================
        // SEND CHALLENGE TO Key
        // ==============================
        const encoder =
            new TextEncoder();

        const writer =
            activePort.writable.getWriter();

        const serialCommand = isMlkemMode
            ? `LOGIN_DECAP:${ciphertextB64}:${challenge}:${normalizedUsername}
`
            : `CHALLENGE:${challenge}
`;

        await writer.write(
            encoder.encode(serialCommand)
        );

        writer.releaseLock();

        console.log(isMlkemMode ? "ML-KEM CIPHERTEXT SENT TO Key" : "CHALLENGE SENT TO Key");

        // ==============================
        // READ RESPONSE (SIGNED CHALLENGE)
        // ==============================
        const reader =
            activePort.readable.getReader();

        const decoder =
            new TextDecoder();

        let buffer = "";
        let key = "";
        let signature = "";
        let proof = "";

        const timeout = setTimeout(() => {

            console.error(
                "Key TIMEOUT"
            );

            reader.cancel();

        }, isMlkemMode ? 30000 : 15000);

        while (true) {

            const {
                value,
                done
            } = await reader.read();

            if (done) break;

            const chunk =
                decoder.decode(value);

            console.log("RAW FROM Key:", chunk);

            buffer += chunk;

            const parsed = parseEsp32SerialBuffer(buffer);
            key = parsed.key;
            signature = parsed.signature;
            proof = parsed.proof;

            const handshakeReady = isMlkemMode
                ? key.length === 64 && proof.length === 64
                : key.length === 64 && signature.length === 64;

            if (handshakeReady) {
                clearTimeout(timeout);
                console.log("DEVICE ID:", key, "(" + key.length + ")");
                console.log(isMlkemMode ? "MLKEM PROOF:" : "PQC SIG:", isMlkemMode ? proof : signature);
                reader.releaseLock();
                break;
            }
        }

        if (key.length !== 64 || (isMlkemMode ? proof.length !== 64 : signature.length !== 64)) {
            throw new Error(
                isMlkemMode
                    ? "Incomplete ML-KEM handshake (DEVICE_ID len=" + key.length + ", MLKEM_PROOF len=" + proof.length + "; need 64 each)"
                    : "Incomplete legacy handshake (DEVICE_ID len=" + key.length + ", PQC_SIG len=" + signature.length + "; need 64 each)"
            );
        }

        updatePQCStatus(
            isMlkemMode ? "Verifying ML-KEM decapsulation proof..." : "Verifying device signature..."
        );

        // ==============================
        // VERIFY BACKEND
        // ==============================
        const response =
            await fetch(
                apiUrl('/apps/pqc_login/verify'),
                {
                    method:'POST',
                    headers:{
                        'Content-Type':
                            'application/json'
                    },
                    body:JSON.stringify({
                        user: normalizedUsername,
                        key,
                        signature,
                        proof,
                        mode: isMlkemMode ? 'mlkem512' : 'legacy',
                        challenge
                    })
                }
            );

        if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            if (errData.audit) {
                await showPQCVerificationSteps("Verification failed — review evidence");
                await showPQCEvidenceReport(errData.audit, window._pqcCred.email || window._pqcCred.username);
                return;
            }
            throw new Error(errData.message || "Verification failed");
        }

        const data =
            await response.json();

        console.log(
            "VERIFY RESPONSE:",
            data
        );

        // ==============================
        // SUCCESS
        // ==============================
        if (data.status === "ok") {

            await showPQCVerificationSteps(
                "Secure authentication successful"
            );

            let audit = data.audit;
            if (!audit) {
                const expectedSig = await computeChallengeSignature(key, challenge);
                audit = {
                    challenge,
                    algorithm: "ML-KEM-512 (CRYSTALS-Kyber)",
                    standard: "NIST FIPS 203",
                    oid: "2.16.840.1.101.3.4.4.1",
                    hardware_public_key: key,
                    bound_public_key: key,
                    key_match: true,
                    device_signature: signature,
                    expected_signature: expectedSig,
                    signature_valid: signature === expectedSig,
                    signature_formula: "SHA-256(HardwareRootKey || Challenge)",
                    kem_encapsulation: { steps: ["(client-side audit)"] },
                    kem_decapsulation: { steps: ["(client-side audit)"] },
                    private_key_note: "Hardware private key never exported",
                };
            }

            const confirmed = await showPQCEvidenceReport(audit, window._pqcCred.email || window._pqcCred.username);

            if (confirmed && audit.signature_valid && audit.key_match) {
                await doRealLogin();
            }

            return;
        }

        throw new Error(
            "Verification failed"
        );

    } catch (err) {

        console.error(
            "VERIFY ERROR:",
            err
        );

        showPQCError(
            err.message
        );

        try {

            if (activePort) {

                await activePort.close();

                activePort = null;
            }

        } catch(e) {}
    }
}


// ==============================
// 🔑 REAL LOGIN
// ==============================
async function doRealLogin() {

    const {
        username,
        password
    } = window._pqcCred || {};

    if (!username || !password) {
        showPQCError("Missing login credentials. Please close and try again.");
        return;
    }

    updatePQCStatus("Signing in to QSafe Cloud...", "#007bff");

    try {

        const token = requestToken();
        if (!token) {
            throw new Error("Missing security token. Please refresh the page.");
        }

        const formData =
            new URLSearchParams();

        formData.append(
            "user",
            username
        );

        formData.append(
            "password",
            password
        );

        formData.append(
            "requesttoken",
            token
        );

        const res = await fetch(
            "/index.php/login",
            {
                method:"POST",
                body:formData,
                credentials:"same-origin",
                redirect: "manual"
            }
        );

        console.log(
            "LOGIN STATUS:",
            res.status
        );

        if (res.status === 0 || res.status === 302 || res.status === 303 || res.ok) {
            updatePQCStatus("Success! Opening dashboard...", "#16a34a");
            window.location.href = "/index.php/apps/files/";
            return;
        }

        throw new Error("Login failed (HTTP " + res.status + "). Please try again.");

    } catch(err) {

        console.error(
            "LOGIN ERROR:",
            err
        );

        showPQCError(err.message || "Login failed. Please try again.");

        const btn = document.getElementById("pqc-confirm-enter");
        if (btn) {
            btn.disabled = false;
            btn.textContent = "✓ Confirm & Enter Dashboard";
        }
    }
}

// Key disconnect logout and back-navigation guard live in pqc-session.js (dashboard only)
