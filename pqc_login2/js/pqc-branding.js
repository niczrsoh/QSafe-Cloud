(function () {
    'use strict';

    const FROM = 'PQCC';
    const TO = 'QSafe Cloud';

    function replaceText(value) {
        if (typeof value !== 'string' || !value.includes(FROM)) {
            return value;
        }
        return value.split(FROM).join(TO);
    }

    function applyBranding() {
        if (typeof window._theme === 'object' && window._theme) {
            ['entity', 'name', 'title'].forEach((key) => {
                if (window._theme[key]) {
                    window._theme[key] = replaceText(window._theme[key]);
                }
            });
            if (window._theme.slogan) {
                window._theme.slogan = replaceText(window._theme.slogan);
            }
        }

        document.title = replaceText(document.title);

        document.querySelectorAll('h1, .entity-name, footer .info, .hidden-visually').forEach((el) => {
            if (el.textContent && el.textContent.includes(FROM)) {
                el.textContent = replaceText(el.textContent);
            }
        });
    }

    function run() {
        applyBranding();
        if (document.body) {
            new MutationObserver(applyBranding).observe(document.body, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
