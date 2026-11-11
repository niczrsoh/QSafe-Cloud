/**
 * Device registration page – Key binding uses the same serial protocol as login.
 */
(function () {
    'use strict';

    let activePort = null;
    let primaryDevice = null;
    let backupDevice = null;
    let registeredUser = '';
    let registeredEmail = '';

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

    function showRegistrationSuccessDialog(email) {
        const existing = document.getElementById('pqc-registration-success-dialog');
        if (existing) existing.remove();

        const loginUrl = apiUrl('/login');
        const overlay = document.createElement('div');
        overlay.id = 'pqc-registration-success-dialog';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;z-index:99999;padding:20px;';
        overlay.innerHTML = `
            <div style="width:100%;max-width:420px;background:white;border-radius:16px;padding:28px;box-shadow:0 24px 60px rgba(15,23,42,0.25);text-align:center;font-family:'Segoe UI',sans-serif;">
                <div style="font-size:38px;margin-bottom:12px;color:#16a34a;">✓</div>
                <h2 style="margin:0 0 10px;color:#0f172a;font-size:22px;">Successfully registered</h2>
                <p style="margin:0 0 20px;color:#475569;font-size:14px;line-height:1.5;">Your account ${email ? '<b>' + email.replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])) + '</b>' : ''} is ready. You can now login using the registered email account.</p>
                <button id="pqc-success-login-btn" type="button" style="width:100%;padding:13px;background:#0f172a;color:white;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;">Back to Login</button>
            </div>
        `;
        document.body.appendChild(overlay);
        document.getElementById('pqc-success-login-btn').onclick = () => {
            window.location.href = loginUrl;
        };
    }

    function setupPasswordToggle(toggleEl, inputEl) {
        if (!toggleEl || !inputEl) {
            return;
        }

        const eyeOpen = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        const eyeClosed = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

        toggleEl.innerHTML = eyeOpen;
        toggleEl.setAttribute('role', 'button');
        toggleEl.setAttribute('aria-label', 'Toggle password visibility');
        toggleEl.setAttribute('tabindex', '0');

        const toggle = () => {
            const isPassword = inputEl.getAttribute('type') === 'password';
            inputEl.setAttribute('type', isPassword ? 'text' : 'password');
            toggleEl.innerHTML = isPassword ? eyeClosed : eyeOpen;
        };

        toggleEl.addEventListener('click', (e) => {
            e.preventDefault();
            toggle();
        });
        toggleEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    }

    function updateSubmitState(submitBtn) {
        if (!submitBtn) return;
        const canSubmit = !!primaryDevice;
        submitBtn.disabled = !canSubmit;
        submitBtn.style.background = canSubmit ? '#0f172a' : '#94a3b8';
        submitBtn.style.cursor = canSubmit ? 'pointer' : 'not-allowed';
    }

    async function bindEsp32Device(slot, bindBtn, keyDisplay, keyValue, keyInput, statusMsg, submitBtn) {
        if (!window.PQCSerial) {
            alert('Device binding module failed to load. Please refresh the page.');
            return;
        }

        bindBtn.disabled = true;
        bindBtn.innerText = 'Communicating...';

        try {
            if (activePort) {
                await window.PQCSerial.closePort(activePort);
                activePort = null;
            }

            const result = await window.PQCSerial.bindEsp32Device({
                apiUrl,
                onStatus: (text) => {
                    statusMsg.innerText = text;
                },
            });

            const device = {
                pqcKey: result.key,
                mlkemPublicKeyB64: result.mlkemPublicKeyB64 || '',
            };

            if (slot === 'backup' && primaryDevice && primaryDevice.pqcKey === device.pqcKey) {
                throw new Error('secondary Key must be a different physical device from the primary Key.');
            }
            if (slot === 'primary' && backupDevice && backupDevice.pqcKey === device.pqcKey) {
                throw new Error('Primary Key must be different from the secondary Key.');
            }

            if (slot === 'primary') {
                primaryDevice = device;
            } else {
                backupDevice = device;
            }

            keyInput.value = device.pqcKey;
            keyValue.innerText = device.pqcKey;
            keyDisplay.style.display = 'block';

            statusMsg.innerHTML = '<span style="color:#22c55e; font-weight:bold;">' + (device.mlkemPublicKeyB64 ? 'ML-KEM device public key detected successfully!' : 'Legacy device ID detected successfully!') + '</span>';
            bindBtn.innerText = slot === 'primary' ? 'Primary Key Bound' : 'secondary Key Bound';
            bindBtn.style.display = 'none';
            updateSubmitState(submitBtn);
        } catch (err) {
            console.error('[Device Register] Binding error:', err);
            statusMsg.innerText = 'Error: ' + (err.message || 'Binding failed');
            bindBtn.innerText = slot === 'primary' ? 'Try Primary Again' : 'Try secondary Again';
            bindBtn.disabled = false;

            if (activePort) {
                await window.PQCSerial.closePort(activePort);
                activePort = null;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const primaryBindBtn = document.getElementById('bind-primary-pqc-btn');
        const primaryKeyDisplay = document.getElementById('primary-pqc-key-display');
        const primaryKeyValue = document.getElementById('primary-pqc-key-value');
        const primaryKeyInput = document.getElementById('pqc-key-input');
        const primaryStatusMsg = document.getElementById('primary-pqc-status-msg');
        const backupBindBtn = document.getElementById('bind-backup-pqc-btn');
        const backupKeyDisplay = document.getElementById('backup-pqc-key-display');
        const backupKeyValue = document.getElementById('backup-pqc-key-value');
        const backupKeyInput = document.getElementById('backup-pqc-key-input');
        const backupStatusMsg = document.getElementById('backup-pqc-status-msg');
        const submitBtn = document.getElementById('submit-btn');
        const form = document.getElementById('pqc-register-form');

        setupPasswordToggle(
            document.getElementById('toggle-password'),
            document.getElementById('pqc-password')
        );
        setupPasswordToggle(
            document.getElementById('toggle-confirm-password'),
            document.getElementById('pqc-confirm-password')
        );

        if (primaryBindBtn) {
            primaryBindBtn.addEventListener('click', () => {
                bindEsp32Device('primary', primaryBindBtn, primaryKeyDisplay, primaryKeyValue, primaryKeyInput, primaryStatusMsg, submitBtn);
            });
        }
        if (backupBindBtn) {
            backupBindBtn.addEventListener('click', () => {
                bindEsp32Device('backup', backupBindBtn, backupKeyDisplay, backupKeyValue, backupKeyInput, backupStatusMsg, submitBtn);
            });
        }

        if (!form) {
            return;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            if (data.password !== data.confirm_password) {
                alert('Passwords do not match!');
                return;
            }

            if (!primaryDevice) {
                alert('Please bind at least the primary Key device first!');
                return;
            }

            submitBtn.innerText = 'Creating Account...';
            submitBtn.disabled = true;

            try {
                const email = (data.email || '').trim().toLowerCase();
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    alert('Please enter a valid email address.');
                    submitBtn.innerText = 'Register & Bind';
                    submitBtn.disabled = false;
                    updateSubmitState(submitBtn);
                    return;
                }

                const username = (data.user || '').trim().toLowerCase();
                if (!username || !/^[a-z0-9_.@-]{3,64}$/.test(username)) {
                    alert('Please enter a valid username using 3-64 letters, numbers, dot, dash, underscore, or @.');
                    submitBtn.innerText = 'Register & Bind';
                    submitBtn.disabled = false;
                    updateSubmitState(submitBtn);
                    return;
                }

                const devices = [primaryDevice];
                if (backupDevice) {
                    devices.push(backupDevice);
                }

                const payload = {
                    user: username,
                    password: data.password,
                    pqcKey: primaryDevice.pqcKey,
                    mlkemPublicKeyB64: primaryDevice.mlkemPublicKeyB64,
                    devices,
                    displayName: data.displayName || '',
                    email
                };

                const headers = { 'Content-Type': 'application/json' };
                const token = requestToken();
                if (token) {
                    headers.requesttoken = token;
                }

                const response = await fetch(apiUrl('/apps/pqc_login/register'), {
                    method: 'POST',
                    headers,
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (result.status === 'success') {
                    registeredUser = result.user || payload.user;
                    registeredEmail = payload.email;
                    showSSSPage(result.sss);
                } else {
                    alert('Error: ' + (result.message || 'Registration failed'));
                    submitBtn.innerText = 'Register & Bind';
                    submitBtn.disabled = false;
                    updateSubmitState(submitBtn);
                }
            } catch (err) {
                console.error('[Device Register]', err);
                alert('An error occurred during registration.');
                submitBtn.innerText = 'Register & Bind';
                submitBtn.disabled = false;
                updateSubmitState(submitBtn);
            }
        });
    });

    function showSSSPage(sss) {
        if (!sss || !sss.share_b) {
            console.error('[Device Register] Recovery data missing from response');
            alert('Account created, but recovery keys could not be generated. Please contact admin.');
            window.location.href = apiUrl('/login');
            return;
        }

        const form = document.getElementById('pqc-register-form');
        const sssContainer = document.getElementById('sss-display-container');
        const shareBEl = document.getElementById('sss-share-b');
        const copyBtn = document.getElementById('copy-share-b');
        const downloadBtn = document.getElementById('download-share-c');
        const finishBtn = document.getElementById('finish-sss-btn');

        if (!form || !sssContainer) return;

        form.style.display = 'none';
        sssContainer.style.display = 'block';

        shareBEl.innerText = sss.share_b;

        copyBtn.onclick = () => {
            navigator.clipboard.writeText(sss.share_b);
            copyBtn.innerText = 'Copied!';
            setTimeout(() => copyBtn.innerText = 'Copy', 2000);
        };

        downloadBtn.onclick = () => {
            const blob = new Blob([sss.share_c], { type: 'application/octet-stream' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'recovery.pqc';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        };

        finishBtn.onclick = async () => {
            const kbaItems = [1, 2, 3].map((index) => ({
                question: document.getElementById('sss-kba-question-' + index)?.value || '',
                answer: document.getElementById('sss-kba-answer-' + index)?.value || '',
            }));
            const selectedQuestions = kbaItems.map((item) => item.question).filter(Boolean);
            const uniqueQuestions = new Set(selectedQuestions);
            if (kbaItems.some((item) => !item.question || !item.answer.trim())) {
                alert('Please set all 3 recovery questions and answers before finishing.');
                return;
            }
            if (uniqueQuestions.size !== 3) {
                alert('Please choose 3 different security questions.');
                return;
            }

            finishBtn.disabled = true;
            finishBtn.innerText = 'Saving recovery setup...';
            try {
                const headers = { 'Content-Type': 'application/json' };
                const token = requestToken();
                if (token) headers.requesttoken = token;

                const response = await fetch(apiUrl('/apps/pqc_login/register'), {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        action: 'setup-kba',
                        user: registeredUser,
                        kbaItems,
                    }),
                });
                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Unable to save recovery question');
                }
                showRegistrationSuccessDialog(registeredEmail || registeredUser);
            } catch (err) {
                alert('KBA setup failed: ' + (err.message || 'Unknown error'));
                finishBtn.disabled = false;
                finishBtn.innerText = 'I have saved my recovery keys';
            }
        };
    }
})();
