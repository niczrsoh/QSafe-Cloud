(function () {
    'use strict';

    let detectedPqcKey = '';
    let detectedMlkemPublicKeyB64 = '';
    let detectedPqcSlot = 'primary';
    let kbaQuestionTimer = null;
    let requiredKbaCorrect = 2;

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

    function setButtonLoading(btn, loading, label) {
        if (!btn) {
            return;
        }
        btn.disabled = loading;
        btn.dataset.originalText = btn.dataset.originalText || btn.textContent;
        btn.textContent = loading ? label : btn.dataset.originalText;
        btn.style.opacity = loading ? '0.7' : '1';
        btn.style.cursor = loading ? 'wait' : 'pointer';
    }

    function initRecovery() {
        const verifyBtn = document.getElementById('verify-recovery-btn');
        const rebindBtn = document.getElementById('rebind-pqc-btn');
        const finalBtn = document.getElementById('final-rebind-btn');
        const freezeBtn = document.getElementById('freeze-account-btn');
        const usernameInput = document.getElementById('recovery-username');

        if (!verifyBtn) {
            return;
        }

        verifyBtn.addEventListener('click', handleVerify);
        if (usernameInput) {
            const scheduleKbaLoad = () => {
                clearTimeout(kbaQuestionTimer);
                kbaQuestionTimer = setTimeout(loadKbaQuestion, 350);
            };
            usernameInput.addEventListener('input', scheduleKbaLoad);
            usernameInput.addEventListener('blur', loadKbaQuestion);
            usernameInput.addEventListener('change', loadKbaQuestion);
            if (usernameInput.value.trim()) {
                loadKbaQuestion();
            }
        }
        if (rebindBtn) {
            rebindBtn.addEventListener('click', handleSerialBind);
        }
        if (finalBtn) {
            finalBtn.addEventListener('click', handleFinalRebind);
        }
        if (freezeBtn) {
            freezeBtn.addEventListener('click', handleFreezeAccount);
        }
    }


    async function loadKbaQuestion() {
        const user = document.getElementById('recovery-username')?.value.trim().toLowerCase() || '';
        const questionEl = document.getElementById('recovery-kba-question');
        if (!questionEl || !user) {
            if (questionEl) questionEl.textContent = 'Enter your username or email to load your questions.';
            return;
        }

        questionEl.textContent = 'Loading security question...';
        try {
            const res = await fetch(apiUrl('/apps/pqc_login/register'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    requesttoken: requestToken(),
                },
                body: JSON.stringify({ action: 'kba-question', user }),
            });
            const result = await res.json();
            if (result.status === 'success') {
                const questions = Array.isArray(result.questions) && result.questions.length ? result.questions : [result.question];
                requiredKbaCorrect = result.requiredCorrect || (questions.length >= 3 ? 2 : 1);
                questionEl.innerHTML = questions.map((question, index) => '<div style="margin-bottom:6px;"><b>Q' + (index + 1) + '.</b> ' + String(question || '').replace(/[&<>]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])) + '</div>').join('') + '<div style="color:#64748b;font-size:11px;margin-top:6px;">Need ' + requiredKbaCorrect + ' correct answer' + (requiredKbaCorrect > 1 ? 's' : '') + '.</div>';
                const answerWrap = document.getElementById('recovery-kba-answers');
                if (answerWrap) {
                    Array.from(answerWrap.querySelectorAll('input')).forEach((input, index) => {
                        input.style.display = index < questions.length ? 'block' : 'none';
                    });
                }
            } else {
                questionEl.textContent = result.message || 'No security question found for this user.';
            }
        } catch (err) {
            console.error('[PQC Recovery] KBA question error:', err);
            questionEl.textContent = 'Unable to load security question.';
        }
    }

    async function handleVerify() {
        const verifyBtn = document.getElementById('verify-recovery-btn');
        const user = document.getElementById('recovery-username').value.trim();
        const shareB = document.getElementById('recovery-share-b').value.trim();
        const kbaAnswers = [1, 2, 3].map((index) => document.getElementById('recovery-kba-answer-' + index)?.value.trim() || '');
        const fileInput = document.getElementById('recovery-share-c');

        if (user) {
            await loadKbaQuestion();
        }

        let shareToUse = shareB;

        if (!shareToUse && fileInput && fileInput.files.length > 0) {
            const text = await fileInput.files[0].text();
            shareToUse = text.trim();
        }

        if (!user || !shareToUse || kbaAnswers.filter(Boolean).length < requiredKbaCorrect) {
            alert('Please provide username, recovery string/file, and at least ' + requiredKbaCorrect + ' security answer' + (requiredKbaCorrect > 1 ? 's' : '') + '.');
            return;
        }

        setButtonLoading(verifyBtn, true, 'Verifying...');

        try {
            const res = await fetch(apiUrl('/apps/pqc_login/register'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    requesttoken: requestToken(),
                },
                body: JSON.stringify({ action: 'recovery-verify', user, share: shareToUse, kbaAnswers }),
            });

            const result = await res.json();
            if (result.status === 'success') {
                document.getElementById('recovery-step-1').style.display = 'none';
                document.getElementById('recovery-step-2').style.display = 'block';
            } else {
                alert('Recovery failed: ' + (result.message || 'Unknown error'));
            }
        } catch (err) {
            console.error('[PQC Recovery] verify error:', err);
            alert('Recovery failed: ' + (err.message || 'Network error'));
        } finally {
            setButtonLoading(verifyBtn, false, 'Verify & Continue');
        }
    }


    async function handleFreezeAccount() {
        const freezeBtn = document.getElementById('freeze-account-btn');
        const confirmed = window.confirm('Freeze this account now? The account will be disabled until an admin unlocks it.');

        if (!confirmed) {
            return;
        }

        setButtonLoading(freezeBtn, true, 'Freezing...');

        try {
            const res = await fetch(apiUrl('/apps/pqc_login/register'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    requesttoken: requestToken(),
                },
                body: JSON.stringify({ action: 'recovery-freeze' }),
            });

            const result = await res.json();
            if (result.status === 'success') {
                document.getElementById('recovery-step-2').style.display = 'none';
                document.getElementById('recovery-step-freeze').style.display = 'block';
            } else {
                alert('Freeze failed: ' + (result.message || 'Unknown error'));
                setButtonLoading(freezeBtn, false, 'Freeze Account');
            }
        } catch (err) {
            console.error('[PQC Recovery] freeze error:', err);
            alert('Freeze failed: ' + (err.message || 'Network error'));
            setButtonLoading(freezeBtn, false, 'Freeze Account');
        }
    }

    async function handleSerialBind() {
        const rebindBtn = document.getElementById('rebind-pqc-btn');
        const statusMsg = document.getElementById('pqc-status-msg');
        const slotInputs = Array.from(document.querySelectorAll('input[name="rebind-slot"]'));

        if (!window.PQCSerial) {
            alert('Device binding module failed to load. Please refresh the page.');
            return;
        }

        setButtonLoading(rebindBtn, true, 'Binding...');

        try {
            const result = await window.PQCSerial.bindEsp32Device({
                apiUrl,
                onStatus: (text) => {
                    if (statusMsg) {
                        statusMsg.textContent = text;
                    }
                },
            });

            detectedPqcKey = result.key;
            detectedMlkemPublicKeyB64 = result.mlkemPublicKeyB64 || '';
            const selectedSlot = slotInputs.find((input) => input.checked)?.value || 'primary';
            detectedPqcSlot = selectedSlot;
            document.getElementById('pqc-key-value').innerText = detectedPqcKey;
            document.getElementById('pqc-key-display').style.display = 'block';

            const finalBtn = document.getElementById('final-rebind-btn');
            finalBtn.disabled = false;
            finalBtn.style.background = '#22c55e';
            finalBtn.style.cursor = 'pointer';

            if (statusMsg) {
                statusMsg.innerHTML = '<span style="color:#22c55e;font-weight:bold;">' + (detectedMlkemPublicKeyB64 ? ('ML-KEM public key detected — click Complete Rebinding as ' + detectedPqcSlot + '.') : ('Legacy device ID detected — click Complete Rebinding as ' + detectedPqcSlot + '.')) + '</span>';
            }
            rebindBtn.style.display = 'none';
        } catch (err) {
            console.error('[PQC Recovery] bind error:', err);
            if (statusMsg) {
                statusMsg.textContent = 'Error: ' + (err.message || 'Binding failed');
            }
            setButtonLoading(rebindBtn, false, 'Try Again');
        }
    }

    async function handleFinalRebind() {
        const finalBtn = document.getElementById('final-rebind-btn');

        if (!detectedPqcKey) {
            alert('Please bind your Key first.');
            return;
        }

        setButtonLoading(finalBtn, true, 'Rebinding...');

        try {
            const res = await fetch(apiUrl('/apps/pqc_login/register'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    requesttoken: requestToken(),
                },
                body: JSON.stringify({ action: 'recovery-rebind', pqcKey: detectedPqcKey, mlkemPublicKeyB64: detectedMlkemPublicKeyB64, slot: detectedPqcSlot }),
            });

            const result = await res.json();
            if (result.status === 'success') {
                showNewSSS(result.sss);
            } else {
                alert('Rebind failed: ' + (result.message || 'Unknown error'));
                setButtonLoading(finalBtn, false, 'Complete Rebinding');
            }
        } catch (err) {
            console.error('[PQC Recovery] rebind error:', err);
            alert('Rebind failed: ' + (err.message || 'Network error'));
            setButtonLoading(finalBtn, false, 'Complete Rebinding');
        }
    }

    function showNewSSS(sss) {
        document.getElementById('recovery-step-2').style.display = 'none';
        document.getElementById('recovery-step-3').style.display = 'block';
        document.getElementById('new-sss-share-b').innerText = sss.share_b;

        document.getElementById('copy-new-share-b').onclick = () => {
            navigator.clipboard.writeText(sss.share_b);
        };

        document.getElementById('download-new-share-c').onclick = () => {
            const blob = new Blob([sss.share_c], { type: 'application/octet-stream' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'recovery.pqc';
            a.click();
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRecovery);
    } else {
        initRecovery();
    }
})();
