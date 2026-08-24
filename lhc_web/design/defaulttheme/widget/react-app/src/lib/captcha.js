let loadedCaptchaScripts = {};

function getRecaptchaApi() {
    try {
        if (window.parent !== window && typeof window.parent.grecaptcha !== 'undefined') {
            return window.parent.grecaptcha;
        }
    } catch (e) {
        // Cross-origin parent, fall back to own window
    }

    if (typeof window.grecaptcha !== 'undefined') {
        return window.grecaptcha;
    }

    return null;
}

function getTurnstileApi() {
    try {
        if (window.parent !== window && typeof window.parent.turnstile !== 'undefined') {
            return window.parent.turnstile;
        }
    } catch (e) {
        // Cross-origin parent, fall back to own window
    }

    if (typeof window.turnstile !== 'undefined') {
        return window.turnstile;
    }

    return null;
}

export function loadCaptchaScript(url, provider, id) {
    return new Promise((resolve, reject) => {
        if (!url) {
            reject(new Error('Captcha URL is empty'));
            return;
        }

        // Do not load the script if the provider API is already available in scope
        if (provider === 'google' && getRecaptchaApi() !== null) {
            resolve();
            return;
        }

        if (provider === 'turnstile' && getTurnstileApi() !== null) {
            resolve();
            return;
        }

        // Prefer parent document when running in iframe mode
        let targetDocument = document;
        try {
            if (window.parent !== window) {
                targetDocument = window.parent.document;
            }
        } catch (e) {
            // Cross-origin parent, fall back to own document
        }

        id = id || ('lhc-captcha-' + url.replace(/[^a-zA-Z0-9]/g, '_'));

        if (loadedCaptchaScripts[id] === true || targetDocument.getElementById(id) !== null) {
            loadedCaptchaScripts[id] = true;
            resolve();
            return;
        }

        loadedCaptchaScripts[id] = true;

        const script = targetDocument.createElement('script');
        script.src = url;
        script.id = id;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            loadedCaptchaScripts[id] = false;
            reject(new Error('Failed to load captcha script: ' + url));
        };

        targetDocument.head.appendChild(script);
    });
}

export function getGoogleRecaptchaToken(siteKey, action) {
    return new Promise((resolve, reject) => {
        const grecaptcha = getRecaptchaApi();

        if (grecaptcha === null) {
            reject(new Error('Google reCAPTCHA is not loaded'));
            return;
        }

        grecaptcha.ready(() => {
            grecaptcha.execute(siteKey, {action: action}).then(resolve).catch(reject);
        });
    });
}

export function getTurnstileToken(siteKey) {
    return new Promise((resolve, reject) => {
        const turnstile = getTurnstileApi();

        if (turnstile === null) {
            reject(new Error('Cloudflare Turnstile is not loaded'));
            return;
        }

        // Render in the same document that owns the Turnstile API
        let targetDocument = document;
        try {
            if (window.parent !== window && window.parent.turnstile === turnstile) {
                targetDocument = window.parent.document;
            }
        } catch (e) {
            // Cross-origin parent, keep current document
        }

        const container = targetDocument.createElement('div');
        container.style.display = 'none';
        targetDocument.body.appendChild(container);

        const widgetId = turnstile.render(container, {
            sitekey: siteKey,
            callback: (token) => {
                resolve(token);
                cleanup();
            },
            'error-callback': () => {
                reject(new Error('Cloudflare Turnstile error'));
                cleanup();
            },
            'expired-callback': () => {
                reject(new Error('Cloudflare Turnstile token expired'));
                cleanup();
            }
        });

        function cleanup() {
            try {
                turnstile.remove(widgetId);
            } catch (e) {}

            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
        }
    });
}

export function resolveChatCaptcha(settings) {
    if (!settings) {
        return Promise.resolve(null);
    }

    const provider = settings.get ? settings.get('provider') : settings.provider;
    const siteKey = settings.get ? settings.get('site_key') : settings.site_key;
    const action = 'widget_chat_start';

    if (provider === 'google') {
        return getGoogleRecaptchaToken(siteKey, action).then(token => ({'g-recaptcha' : token}));
    }

    if (provider === 'turnstile') {
        return getTurnstileToken(siteKey).then(token => ({'cf-turnstile-response' : token}));
    }

    return Promise.resolve(null);
}
