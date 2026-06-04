/*!
 * PayFast Laravel Package — Wix (or generic) checkout injector (vanilla JS, no jQuery)
 *
 * Hides the storefront's native checkout button and injects a "Pay Now" button
 * that hands the buyer off to <YOUR_LARAVEL_DOMAIN>/payment/checkout via a signed URL.
 *
 * The HMAC signature is generated server-side at /api/payfast/sign-checkout —
 * the secret never reaches the browser. Origin is enforced by CORS on the Laravel side.
 *
 * Drop into the storefront (Wix → Settings → Custom Code, Shopify → theme.liquid,
 * etc.). Update CONFIG below for your Laravel URL + success URL.
 *
 * Debug: set window.PAYFAST_DEBUG = false to silence logs.
 */
(function (window, document) {
    'use strict';

    var DEBUG = window.PAYFAST_DEBUG !== false; // default ON
    function log() {
        if (!DEBUG) return;
        var args = Array.prototype.slice.call(arguments);
        args.unshift('%c[payfast]', 'color:#ff5722;font-weight:bold');
        console.log.apply(console, args);
    }
    function warn() {
        var args = Array.prototype.slice.call(arguments);
        args.unshift('%c[payfast]', 'color:#b91c1c;font-weight:bold');
        console.warn.apply(console, args);
    }

    log('script start', { href: window.location.href });

    // ---- Configuration ----------------------------------------------------
    // EDIT these two URLs before publishing.
    var CONFIG = {
        signEndpoint: 'https://payments.example.com/api/payfast/sign-checkout',
        successUrl: 'https://example.com/checkout/success',
        pageMatcher: /\/checkout(?:\?|$|\/)/i,
        defaultButtonSelector: 'button[data-hook="PlaceOrderButton"], button[data-hook="PlaceOrderButtonDataHooks.PlaceOrderButton"], button[data-hook="checkout-button"]',
        injectAnchors: [
            'section[aria-labelledby="summary-section"] [data-hook="TotalsSectionDataHooks.TotalSection"]',
            'section[aria-labelledby="summary-section"] [data-hook="TotalsSectionDataHooks.TotalsSectionWrapper"]',
            'section[aria-labelledby="summary-section"]',
            '[data-hook="CheckoutAppDataHook.twoColumnViewSummary"]',
            '[data-hook="checkout:summary:after"]',
            '[data-hook="summary-section"]',
        ],
        debounceMs: 250,
    };
    log('config', CONFIG);

    var pathPlusQuery = window.location.pathname + window.location.search;
    if (!CONFIG.pageMatcher.test(pathPlusQuery)) {
        warn('pageMatcher did NOT match ? injector aborted.', { pathPlusQuery: pathPlusQuery });
        return;
    }
    log('pageMatcher matched');

    // ---- CSS injection ----------------------------------------------------
    var CSS = (
        '#payfast-paynow-wrapper{margin:16px 0 8px;display:flex;justify-content:center;z-index:9999;position:relative;}' +
        '#payfast-paynow-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:14px 28px;font-size:16px;font-weight:700;letter-spacing:0.3px;color:#fff;background:linear-gradient(120deg,#004122,#040404,#06743f,#1a1a1a);background-size:300% 300%;border:none;border-radius:4px;cursor:pointer;text-decoration:none;text-align:center;box-shadow:0 6px 18px rgba(255,87,34,0.35),0 2px 4px rgba(0,0,0,0.1);transition:transform .15s ease,box-shadow .15s ease;animation:payfast-shimmer 3.2s ease infinite;font-family:inherit;}' +
        '#payfast-paynow-btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(255,87,34,0.45),0 3px 6px rgba(0,0,0,0.05);}' +
        '#payfast-paynow-btn:active{transform:translateY(0);}' +
        '#payfast-paynow-btn[disabled]{opacity:.6;cursor:wait;}' +
        '#payfast-paynow-btn .payfast-spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:payfast-spin .7s linear infinite;display:none;}' +
        '#payfast-paynow-btn.is-loading .payfast-spinner{display:inline-block;}' +
        '#payfast-paynow-btn.is-loading .payfast-label::after{content:"?";}' +
        '#payfast-paynow-error{margin-top:8px;color:#b91c1c;font-size:12px;text-align:center;}' +
        '@keyframes payfast-shimmer{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}' +
        '@keyframes payfast-spin{to{transform:rotate(360deg)}}' +
        CONFIG.defaultButtonSelector + '{display:none !important;}'
    );

    function injectStylesOnce() {
        if (document.getElementById('payfast-paynow-styles')) {
            log('styles already injected');
            return;
        }
        var style = document.createElement('style');
        style.id = 'payfast-paynow-styles';
        style.type = 'text/css';
        style.appendChild(document.createTextNode(CSS));
        document.head.appendChild(style);
        log('styles injected');
        var hiddenCount = document.querySelectorAll(CONFIG.defaultButtonSelector).length;
        log('default checkout buttons hidden =', hiddenCount);
    }

    // ---- Helpers ----------------------------------------------------------
    function readQueryParam(name) {
        var match = new RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
        return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : null;
    }

    function parseAmount(text) {
        if (!text) return null;
        var clean = String(text).replace(/[^\d.,]/g, '').replace(/,/g, '');
        var n = parseFloat(clean);
        return isFinite(n) ? n : null;
    }

    function textOfLast(selector) {
        var nodes = document.querySelectorAll(selector);
        if (!nodes.length) return '';
        return (nodes[nodes.length - 1].textContent || '').trim();
    }

    function inputValue() {
        for (var i = 0; i < arguments.length; i++) {
            var el = document.querySelector(arguments[i]);
            if (el && 'value' in el && el.value) return el.value;
        }
        return null;
    }

    function textValue() {
        for (var i = 0; i < arguments.length; i++) {
            var el = document.querySelector(arguments[i]);
            if (el) {
                var t = (el.textContent || '').trim();
                if (t) return t;
            }
        }
        return null;
    }

    function splitFullName(full) {
        if (!full) return [null, null];
        var parts = String(full).trim().split(/\s+/);
        if (parts.length === 1) return [parts[0], null];
        return [parts[0], parts.slice(1).join(' ')];
    }

    function scrapeCheckoutData() {
        var checkoutId = readQueryParam('checkoutId') || readQueryParam('checkout_id');

        var totalA = document.querySelectorAll('[data-hook="TotalsSectionDataHooks.TotalRowValue"]');
        var totalB = document.querySelectorAll('[data-hook="totals-row-value-text"]');
        var totalText = textOfLast('[data-hook="TotalsSectionDataHooks.TotalRowValue"]')
            || textOfLast('[data-hook="totals-row-value-text"]');
        var amount = parseAmount(totalText);

        // Inputs first (priority). When Wix collapses the customer-details step
        // it replaces inputs with read-only summary text — fall back to those.
        var firstName = inputValue(
            '[data-hook="form-field-first_name"] input',
            'input[aria-label="First name"]',
            'input[name="firstName"]',
            'input#firstName'
        );
        var lastName = inputValue(
            '[data-hook="form-field-last_name"] input',
            'input[aria-label="Last name"]',
            'input[name="lastName"]',
            'input#lastName'
        );
        var email = inputValue(
            '[data-hook="form-field-email"] input',
            'input[type="email"]',
            'input[aria-label="Email"]',
            'input[name="email"]'
        );
        var phone = inputValue(
            '[data-hook="form-field-phone"] input',
            'input[type="phone"]',
            'input[type="tel"]',
            'input[aria-label="Phone"]',
            'input[name="phone"]'
        );

        // Fallback to collapsed ContactAndAddressSummary text
        if (!email) {
            email = textValue('[data-hook="ContactAndAddressSummary.email"]');
        }
        if (!phone) {
            phone = textValue('[data-hook="ContactAndAddressSummary.phone"]');
        }
        if (!firstName || !lastName) {
            var fullName = textValue('[data-hook="ContactAndAddressSummary.fullName"]');
            if (fullName) {
                var split = splitFullName(fullName);
                if (!firstName) firstName = split[0];
                if (!lastName) lastName = split[1];
            }
        }

        var payload = {
            checkout_id: checkoutId || ('wix_' + Date.now()),
            amount: amount,
            currency: 'PKR',
            email: email || null,
            first_name: firstName || null,
            last_name: lastName || null,
            mobile: phone || null,
            return_url: CONFIG.successUrl,
        };

        log('scrape', {
            payload: payload,
            totalRowValueCount: totalA.length,
            totalsRowValueTextCount: totalB.length,
            totalText: totalText,
        });

        return payload;
    }

    // ---- State ------------------------------------------------------------
    var state = {
        signedUrl: null,
        lastPayloadHash: null,
        signing: false,
        error: null,
        lastAttemptAt: 0,
        consecutiveFailures: 0,
    };

    var FAILURE_COOLDOWN_MS = 15000; // after a failure, don't retry for 15s

    function payloadHash(p) {
        return [p.checkout_id, p.amount, p.currency, p.email, p.first_name, p.last_name, p.mobile].join('|');
    }

    function signPayload(payload) {
        log('POST sign endpoint', CONFIG.signEndpoint, payload);
        return fetch(CONFIG.signEndpoint, {
            method: 'POST',
            mode: 'cors',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        }).then(function (response) {
            return response.text().then(function (text) {
                var body;
                try { body = text ? JSON.parse(text) : null; } catch (e) { body = { raw: text }; }
                if (!response.ok) {
                    var err = new Error('HTTP ' + response.status);
                    err.status = response.status;
                    err.body = body;
                    throw err;
                }
                return body;
            });
        });
    }

    function refreshSignedUrl(force) {
        var payload = scrapeCheckoutData();

        if (!payload.amount || payload.amount <= 0) {
            state.signedUrl = null;
            state.error = 'Waiting for cart total?';
            warn('skipping sign: amount missing or <= 0', { amount: payload.amount });
            renderButton();
            return;
        }

        var hash = payloadHash(payload);
        if (!force && hash === state.lastPayloadHash && state.signedUrl) {
            log('payload unchanged, skip re-sign');
            return;
        }

        if (state.signing) {
            log('already signing, skip duplicate request');
            return;
        }

        // After a failure, back off ? don't hammer a broken endpoint
        if (!force && state.consecutiveFailures > 0) {
            var since = Date.now() - state.lastAttemptAt;
            if (since < FAILURE_COOLDOWN_MS) {
                log('in failure cooldown, skip (' + Math.round((FAILURE_COOLDOWN_MS - since) / 1000) + 's left)');
                return;
            }
        }

        state.lastAttemptAt = Date.now();

        state.signing = true;
        state.error = null;
        renderButton();

        signPayload(payload).then(function (resp) {
            log('sign endpoint OK', resp);
            state.signedUrl = resp && resp.url ? resp.url : null;
            state.lastPayloadHash = hash;
            state.signing = false;
            state.consecutiveFailures = 0;
            renderButton();
        }).catch(function (err) {
            state.consecutiveFailures++;
            warn('sign endpoint FAILED (#' + state.consecutiveFailures + ', backing off ' + (FAILURE_COOLDOWN_MS / 1000) + 's)', {
                message: err && err.message,
                status: err && err.status,
                body: err && err.body,
                likelyCause: !err || !err.status
                    ? 'CORS or network ? endpoint unreachable from this origin'
                    : err.status === 404
                        ? 'Route /api/wix/sign-checkout not deployed on prod ? git pull + php artisan optimize:clear'
                        : 'HTTP error from server',
            });
            state.signing = false;
            state.signedUrl = null;
            state.error = (err && err.body && err.body.message)
                || (!err.status ? 'Network/CORS blocked the sign request.'
                    : err.status === 404 ? 'Payment endpoint not deployed yet.'
                        : 'Could not prepare payment URL.');
            renderButton();
        });
    }

    // ---- Render -----------------------------------------------------------
    function findAnchor() {
        for (var i = 0; i < CONFIG.injectAnchors.length; i++) {
            var sel = CONFIG.injectAnchors[i];
            var el = document.querySelector(sel);
            if (el) {
                log('anchor found at index', i, sel);
                return el;
            }
        }
        warn('NO anchor found from candidates', CONFIG.injectAnchors);
        return null;
    }

    function ensureMounted() {
        var existing = document.getElementById('payfast-paynow-wrapper');
        if (existing) return existing;

        var anchor = findAnchor();
        if (!anchor) return null;

        var wrap = document.createElement('div');
        wrap.id = 'payfast-paynow-wrapper';
        wrap.innerHTML =
            '<div style="width:100%">' +
            '<button type="button" id="payfast-paynow-btn" disabled>' +
            '<span class="payfast-spinner" aria-hidden="true"></span>' +
            '<span class="payfast-label">Preparing checkout</span>' +
            '</button>' +
            '<div id="payfast-paynow-error" style="display:none"></div>' +
            '</div>';

        anchor.parentNode.insertBefore(wrap, anchor.nextSibling);
        log('button mounted after anchor', anchor);

        wrap.addEventListener('click', function (e) {
            var btn = e.target.closest('#payfast-paynow-btn');
            if (!btn) return;
            e.preventDefault();
            log('Pay Now clicked', { signedUrl: state.signedUrl });
            if (state.signedUrl) {
                window.open(state.signedUrl, '_blank', 'noopener,noreferrer');
            }
        });

        return wrap;
    }

    function renderButton() {
        var wrap = ensureMounted();
        if (!wrap) return;

        var btn = wrap.querySelector('#payfast-paynow-btn');
        var err = wrap.querySelector('#payfast-paynow-error');
        var label = btn && btn.querySelector('.payfast-label');
        var payload = scrapeCheckoutData();

        if (state.signing) {
            btn.disabled = true;
            btn.classList.add('is-loading');
            if (label) label.textContent = 'Preparing checkout';
            err.style.display = 'none';
            err.textContent = '';
            return;
        }

        if (state.error || !state.signedUrl) {
            btn.disabled = true;
            btn.classList.remove('is-loading');
            if (label) label.textContent = state.error ? 'Try again' : 'Pay now';
            if (state.error) {
                err.textContent = state.error;
                err.style.display = '';
            } else {
                err.style.display = 'none';
                err.textContent = '';
            }
            return;
        }

        var labelText = 'Pay online Rs ' + (payload.amount ? Number(payload.amount).toLocaleString() : '');
        btn.disabled = false;
        btn.classList.remove('is-loading');
        if (label) label.textContent = labelText.trim();
        err.style.display = 'none';
        err.textContent = '';
    }

    // ---- Re-sign on input changes (debounced) -----------------------------
    var debounceTimer = null;
    function scheduleRefresh() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshSignedUrl, CONFIG.debounceMs);
    }

    function bindWatchers() {
        document.addEventListener('input', scheduleRefresh, true);
        document.addEventListener('change', scheduleRefresh, true);
        log('input watchers bound');

        if ('MutationObserver' in window) {
            var mo = new MutationObserver(function () {
                ensureMounted();
                scheduleRefresh();
            });
            mo.observe(document.body, { childList: true, subtree: true });
            log('MutationObserver attached');
        } else {
            warn('MutationObserver unavailable');
        }
    }

    // ---- Boot -------------------------------------------------------------
    function boot() {
        log('boot ? DOM ready');
        injectStylesOnce();
        var mounted = ensureMounted();
        if (!mounted) {
            warn('initial mount FAILED ? will retry as Wix renders. Check anchor selectors above.');
        }
        refreshSignedUrl();
        bindWatchers();
        log('boot complete', { mounted: !!mounted });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        log('DOM already ready, booting immediately');
        boot();
    }

    // Expose debug handle
    window.PAYFAST = {
        config: CONFIG,
        state: state,
        scrape: scrapeCheckoutData,
        refresh: refreshSignedUrl,
        mount: ensureMounted,
    };
    log('window.PAYFAST exposed ? try PAYFAST.scrape(), PAYFAST.refresh(), PAYFAST.mount()');
})(window, document);
