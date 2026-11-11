/**
 * Logged-in session behaviour: Key disconnect logout, back-navigation guard.
 */
(function () {
    'use strict';

    if ('serial' in navigator) {
        navigator.serial.addEventListener('disconnect', async () => {
            console.warn('[PQC] Key disconnected');

            const overlay = document.createElement('div');
            overlay.innerHTML = `
                <div style="
                    position:fixed;top:0;left:0;width:100%;height:100%;
                    background:rgba(0,0,0,0.65);backdrop-filter:blur(8px);
                    z-index:999999;display:flex;align-items:center;justify-content:center;
                    font-family:'Segoe UI',sans-serif;
                ">
                    <div style="
                        width:380px;background:white;border-radius:18px;padding:35px;
                        text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.35);
                    ">
                        <div style="font-size:54px;margin-bottom:10px;">🔒</div>
                        <h2 style="margin:0;color:#222;font-weight:700;">PQC Device Removed</h2>
                        <p style="margin-top:15px;color:#666;font-size:15px;line-height:1.5;">
                            Secure hardware disconnected.<br><br>
                            Session termination in progress...
                        </p>
                        <div style="
                            margin-top:25px;padding:14px;background:#f3f6ff;
                            border-radius:10px;color:#0056d2;font-weight:600;
                        ">
                            Secure logout executing...
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';

            try {
                await fetch('/index.php/apps/pqc_login/destroy-session', {
                    method: 'POST',
                    credentials: 'same-origin'
                });
            } catch (err) {
                console.error('[PQC] Logout error:', err);
            }

            localStorage.clear();
            sessionStorage.clear();

            if ('caches' in window) {
                caches.keys().then((names) => {
                    names.forEach((name) => caches.delete(name));
                });
            }

            window.history.pushState(null, '', '/index.php/login');
            setTimeout(() => {
                window.location.href = '/index.php/login';
            }, 1800);
        });
    }

    window.addEventListener('pageshow', (event) => {
        if (
            event.persisted ||
            (window.performance.navigation &&
                window.performance.navigation.type === 2)
        ) {
            console.warn('[PQC] Back navigation blocked');
            window.location.reload();
        }
    });
})();
