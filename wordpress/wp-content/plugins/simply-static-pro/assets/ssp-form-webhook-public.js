(function(){
'use strict';
if (window.__SSP_WEBHOOK_INIT__) { return; }
window.__SSP_WEBHOOK_INIT__ = true;

// Get options from JSON file.
var form_config_element = document.querySelector("meta[name='ssp-config-path']");

if (null !== form_config_element) {
    let config_path = form_config_element.getAttribute("content");
    let version_element = document.querySelector("meta[name='ssp-config-version']");
    let version_suffix = '';
    if (null !== version_element) {
        let v = version_element.getAttribute('content');
        if (v) {
            version_suffix = '?ver=' + encodeURIComponent(v);
        }
    }
    let config_url = window.location.origin + config_path + 'forms.json' + version_suffix;


    function submitForm(url, settings, data, formEl) {
        // Prepare data for request.
        let requestData = {
            method: "POST",
            body: data
        };

        // Build headers: merge custom headers and add a safe default for services like Formspree
        // which return JSON (and proper CORS) when Accept: application/json is sent.
        let mergedHeaders = new Headers();

        // Maybe add custom header(s).
        if (settings.form_custom_headers) {
            if (settings.form_custom_headers.includes(',')) {
                // Multiple headers separated by commas
                let headersData = settings.form_custom_headers.split(',');
                headersData.forEach((header) => {
                    if (!header) { return; }
                    let header_parts = header.split(':');
                    if (!header_parts || header_parts.length < 2) { return; }
                    let name = (header_parts[0] || '').trim();
                    let value = (header_parts.slice(1).join(':') || '').trim();
                    if (name) { mergedHeaders.set(name, value); }
                });
            } else {
                // Single header
                let header_parts = settings.form_custom_headers.split(':');
                let name = (header_parts[0] || '').trim();
                let value = (header_parts.slice(1).join(':') || '').trim();
                if (name) { mergedHeaders.set(name, value); }
            }
        }

        // Ensure Accept header prefers JSON to avoid redirect-based HTML flows (helps with CORS on Formspree, etc.)
        if (!mergedHeaders.has('Accept')) {
            mergedHeaders.set('Accept', 'application/json');
        }

        requestData.headers = mergedHeaders;

        // Send data via fetch to URL
        // Do not auto-follow cross-origin redirects; treat redirect responses as success for webhook-style endpoints.
        requestData.redirect = 'manual';
        requestData.mode = 'cors';
        requestData.credentials = 'omit';

        fetch(url, requestData).then(response => {
            // Success if:
            // - 2xx OK
            // - opaqueredirect (redirect blocked details, typical with manual on cross-origin)
            // - explicit 3xx status codes we can read
            const isRedirectLike = response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400);
            if (response.ok || isRedirectLike) {
                handleMessage(settings, false, formEl);
            } else {
                // Optional: surface status in console for debugging
                try { console.debug('[SSP] Webhook non-success status', { url: url, status: response.status, type: response.type }); } catch(e) {}
                handleMessage(settings, true, formEl);
            }
        }).catch(error => {
            // Network-level failures: show error and keep the form visible so users can retry
            try { console.debug('[SSP] Webhook network error', error); } catch(e) {}
            handleMessage(settings, true, formEl);
        });
    }

    // Cache of forms.json to allow hidden-name lookups across handlers
    var __SSP_FORMS_CONFIG__ = null;

    function manageForm(config_url, candidateIds, form) {
        // candidateIds can be a single id or an array of possible ids (strings/numbers)
        const ids = Array.isArray(candidateIds) ? candidateIds.filter(Boolean) : [candidateIds].filter(Boolean);
        // If we have a cached config, use it, otherwise load and cache
        const loadConfig = __SSP_FORMS_CONFIG__ ? Promise.resolve(__SSP_FORMS_CONFIG__) : fetch(config_url)
            .then(response => {
                if (!response.ok) {
                    throw new Error("HTTP error " + response.status);
                }
                return response.json();
            })
            .then(json => {
                __SSP_FORMS_CONFIG__ = json;
                return json;
            });

        loadConfig.then(json => {
                let settings = null;
                if (Array.isArray(json)) {
                    // Normalization helpers
                    const norm = (v) => (v == null ? '' : String(v).trim());
                    const stripHash = (v) => norm(v).replace(/^#/, '');
                    const cf7Num = (v) => {
                        // Extract numeric CF7 id from patterns like wpcf7-f107-p123-o1 or unit tags
                        const s = norm(v);
                        const m = s.match(/wpcf7-f(\d+)-p/);
                        if (m && m[1]) return m[1];
                        // If it's just a number string, return it
                        if (/^\d+$/.test(s)) return s;
                        return '';
                    };
                    const gfNum = (v) => {
                        // Extract numeric GF id from patterns like gform_12 or gform_wrapper_12
                        const s = norm(v);
                        let m = s.match(/gform(?:_wrapper)?_(\d+)/i);
                        if (m && m[1]) return m[1];
                        if (/^\d+$/.test(s)) return s;
                        return '';
                    };
                    const forminatorNum = (v) => {
                        // Extract numeric Forminator id from patterns like forminator-form-123 or forminator-module-123
                        const s = norm(v);
                        let m = s.match(/forminator-(?:form|module)-(\d+)/i);
                        if (m && m[1]) return m[1];
                        // Some forms include hidden input name="form_id" which is numeric
                        if (/^\d+$/.test(s)) return s;
                        return '';
                    };

                    // Prepare normalized candidates with variants
                    const normCandidates = [];

                    // Augment candidates with hidden input values based on configured hidden names (for "Other Plugin" or generic use)
                    try {
                        json.forEach(function(cfg){
                            if (!cfg || !cfg.form_hidden_name) return;
                            var inp = form.querySelector('input[name="' + cfg.form_hidden_name + '"]');
                            if (inp && inp.value) {
                                ids.push(inp.value);
                            }
                        });
                    } catch(e) { /* noop */ }
                    for (let i = 0; i < ids.length; i++) {
                        const cid = ids[i];
                        const a = norm(cid);
                        const b = stripHash(cid);
                        const nCf7 = cf7Num(cid);
                        const nGf = gfNum(cid);
                        const nFo = forminatorNum(cid);
                        [a, b].forEach(val => { if (val && normCandidates.indexOf(val) === -1) normCandidates.push(val); });
                        if (nCf7 && normCandidates.indexOf(nCf7) === -1) normCandidates.push(nCf7);
                        if (nGf && normCandidates.indexOf(nGf) === -1) normCandidates.push(nGf);
                        if (nFo && normCandidates.indexOf(nFo) === -1) normCandidates.push(nFo);
                    }

                    // Try to match across all settings
                    settings = json.find(x => {
                        const sidRaw = x && x.form_id;
                        const sid = stripHash(sidRaw);
                        const sidNumCf7 = cf7Num(sidRaw);
                        const sidNumGf = gfNum(sidRaw);
                        const sidNumFo = forminatorNum(sidRaw);
                        // Exact equals, or substring either direction, or numeric id equals (for CF7/GF)
                        return normCandidates.some(c => {
                            const cc = stripHash(c);
                            return (
                                sid === cc ||
                                (sid && cc && (sid.indexOf(cc) !== -1 || cc.indexOf(sid) !== -1)) ||
                                (sidNumCf7 && cc && sidNumCf7 === cf7Num(cc)) ||
                                (sidNumGf && cc && sidNumGf === gfNum(cc)) ||
                                (sidNumFo && cc && sidNumFo === forminatorNum(cc))
                            );
                        });
                    });
                }
                if (settings) {
                    let data = new FormData(form);

                    // If Cloudflare Turnstile is active on this form, route through our WP REST proxy
                    // using the origin REST base provided in forms.json (not rewritten during export).
                    var hasTurnstile = !!form.querySelector('.cf-turnstile');
                    // Check for Google reCAPTCHA v3 (hidden input with class g-recaptcha-response)
                    var recaptchaInput = form.querySelector('input.g-recaptcha-response[data-sitekey]');
                    var hasRecaptcha = !!recaptchaInput;
                    var restBase = '';
                    // Prefer rest_base from forms.json (origin URL is not rewritten by Simply Static)
                    if ((hasTurnstile || hasRecaptcha) && settings.rest_base && typeof settings.rest_base === 'string') {
                        restBase = settings.rest_base;
                    }
                    var targetUrl = settings.form_webhook;

                    if (hasTurnstile && restBase && typeof targetUrl === 'string' && targetUrl) {
                        // Ensure restBase ends with a single slash
                        try {
                            if (restBase.slice(-1) !== '/') { restBase = restBase + '/'; }
                        } catch(e) { /* noop */ }
                        targetUrl = restBase + 'simplystatic/v1/turnstile/submit?forward_to=' + encodeURIComponent(settings.form_webhook);
                        submitForm(targetUrl, settings, data, form);
                    } else if (hasRecaptcha && restBase && typeof targetUrl === 'string' && targetUrl && typeof grecaptcha !== 'undefined') {
                        // Google reCAPTCHA v3: execute and get token before submitting
                        var siteKey = recaptchaInput.getAttribute('data-sitekey');
                        try {
                            if (restBase.slice(-1) !== '/') { restBase = restBase + '/'; }
                        } catch(e) { /* noop */ }
                        grecaptcha.ready(function() {
                            grecaptcha.execute(siteKey, {action: 'submit'}).then(function(token) {
                                // Set the token in the hidden input and form data
                                recaptchaInput.value = token;
                                data.set('g-recaptcha-response', token);
                                var recaptchaTargetUrl = restBase + 'simplystatic/v1/recaptcha/submit?forward_to=' + encodeURIComponent(settings.form_webhook);
                                submitForm(recaptchaTargetUrl, settings, data, form);
                            }).catch(function(err) {
                                console.error('[SSP] reCAPTCHA execute error:', err);
                                handleMessage(settings, true, form);
                            });
                        });
                    } else {
                        submitForm(targetUrl, settings, data, form);
                    }
                } else {
                    // If no settings found, show a clear inline message to assist debugging
                    const fallbackSettings = {
                        form_success_message: '<strong>Form submitted</strong> (no matching Simply Static Pro form settings found).',
                        form_error_message: 'Form submission could not be mapped to Simply Static Pro form settings.',
                        form_plugin: ''
                    };
                    handleMessage(fallbackSettings, true, form);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function handleMessage(settings, error = false, formEl) {
        // Simple, deterministic UI handling per requested approach:
        // Use the form_id from forms.json to locate the element to hide, and render the message next to it.
        // No wrapper detection, no page reload, no redirects.

        // Build feedback node
        var notice = document.createElement('div');
        notice.className = 'ssp-form-response';
        notice.setAttribute('role', 'alert');
        notice.style.cssText = 'width:100%;max-width:100%;margin:0;';

        var message = document.createElement('div');
        message.className = error ? 'ssp-form-message ssp-error' : 'ssp-form-message ssp-success';
        message.style.cssText = 'width:100%;background:' + (error ? '#e24b4b' : '#58b348') + ';color:#fff;text-align:center;padding:10px;border-radius:3px;';
        var successText = settings && settings.form_success_message ? settings.form_success_message : 'Thanks! Your message has been sent.';
        var errorText = settings && settings.form_error_message ? settings.form_error_message : 'Sorry, something went wrong. Please try again.';
        message.innerHTML = error ? errorText : successText;
        notice.appendChild(message);

        // Primary target resolution
        var target = null;
        if (settings && settings.form_id) {
            // Default: element with id === settings.form_id
            try { target = document.getElementById(String(settings.form_id)); } catch(e) { /* noop */ }
        }
        // Fallback to the actual submitted form element
        if (!target && formEl) { target = formEl; }

        // If still no target, last resort: append to body
        if (!target) {
            document.body.appendChild(notice);
            return;
        }

        // Success: hide target and show message after it
        if (!error) {
            try { target.style.display = 'none'; } catch(e) { /* noop */ }
            if (target.parentNode) {
                if (target.nextSibling) {
                    target.parentNode.insertBefore(notice, target.nextSibling);
                } else {
                    target.parentNode.appendChild(notice);
                }
            } else {
                document.body.appendChild(notice);
            }
            return;
        }

        // Error: do not hide the target; render message before it so user can correct inputs
        if (target.parentNode) {
            target.parentNode.insertBefore(notice, target);
        } else {
            document.body.appendChild(notice);
        }
    }

    function modifyFormAttributes(form) {
        form.removeAttribute("action");
        form.removeAttribute("method");
        form.removeAttribute("enctype");
        form.removeAttribute("target");
    }

    document.addEventListener("DOMContentLoaded", function () {
        const isStatic = !!document.querySelector("meta[name='ssp-config-path']");

        // On static builds, neutralize jQuery AJAX calls to admin-ajax.php to prevent Fluent Forms from attempting WP AJAX
        if (isStatic && window.jQuery && window.jQuery.ajax) {
            try {
                const $ = window.jQuery;
                const origAjax = $.ajax;
                $.ajax = function(url, options){
                    const isString = (typeof url === 'string');
                    const opts = isString ? (options || {}) : (url || {});
                    const u = isString ? url : (opts && opts.url);
                    // Detect admin-ajax or WPForms submit action in data
                    const data = opts && opts.data;
                    const hasWPFormsAction = !!(data && (
                        (typeof data === 'string' && (data.indexOf('wpforms') !== -1 || data.indexOf('action=wpforms') !== -1 || data.indexOf('action=wpforms_submit') !== -1)) ||
                        (typeof data === 'object' && data.action && (''+data.action).indexOf('wpforms') !== -1)
                    ));
                    const isAdminAjax = !!(u && u.indexOf('admin-ajax.php') !== -1);
                    if (isAdminAjax || hasWPFormsAction) {
                        // Return a jQuery-compatible rejected Deferred so plugin error handlers don't crash
                        if ($.Deferred) {
                            const d = $.Deferred();
                            const jqXHR = { responseText: '', status: 404, statusText: 'Not Found' };
                            d.reject(jqXHR, 'error', 'Not Found');
                            return d.promise();
                        }
                        // Fallback to a native Promise rejection with a jqXHR-like object
                        return Promise.reject({ responseText: '', status: 404, statusText: 'Not Found' });
                    }
                    return origAjax.apply(this, arguments);
                };
                if ($.post) {
                    const origPost = $.post;
                    $.post = function(url){
                        if (url && url.indexOf('admin-ajax.php') !== -1) {
                            if ($.Deferred) {
                                const d = $.Deferred();
                                const jqXHR = { responseText: '', status: 404, statusText: 'Not Found' };
                                d.reject(jqXHR, 'error', 'Not Found');
                                return d.promise();
                            }
                            return Promise.reject({ responseText: '', status: 404, statusText: 'Not Found' });
                        }
                        return origPost.apply(this, arguments);
                    };
                }
            } catch (e) { /* noop */ }
        }

        const allFormRoots = document.querySelectorAll(
            ".wpcf7 form, .wpcf7-form, .gform_wrapper form, .gform_wrapper, .wpforms-container form, .elementor-form, .wsf-form form, form.wsf-form, .ws-form form, form.ws-form, .frm-fluent-form, .brxe-form, .brxe-brf-pro-forms, .wp-block-kadence-form form, .forminator-custom-form"
        );

        allFormRoots.forEach((root) => {
            // Ensure we have an actual <form> element
            let form = (root && root.tagName && root.tagName.toLowerCase() === 'form') ? root : (root.querySelector && root.querySelector('form'));
            if (!form) { return; }
            if (form.dataset && form.dataset.sspBound === '1') { return; }
            if (form.dataset) { form.dataset.sspBound = '1'; }
            modifyFormAttributes(form);

            // Inputs
            const inputs = form.querySelectorAll("input");
            const textareas = form.querySelectorAll("textarea");
            const selects = form.querySelectorAll("select");

            // Add HTML required attribute based on aria-required="true" seen in many plugins
            inputs.forEach((el) => {
                if (el.getAttribute("aria-required") === "true") {
                    el.required = true;
                }
            });
            textareas.forEach((el) => {
                if (el.getAttribute("aria-required") === "true") {
                    el.required = true;
                }
            });
            selects.forEach((el) => {
                if (el.getAttribute("aria-required") === "true") {
                    el.required = true;
                }
            });


            // On static builds, for Gravity Forms specifically, intercept submit button clicks early (capture)
            if (isStatic && (
                (form.closest && form.closest('.gform_wrapper')) ||
                (form.className && typeof form.className === 'string' && form.className.includes('gform_wrapper')) ||
                (form.id && typeof form.id === 'string' && form.id.includes('gform_'))
            )) {
                // Gravity Forms often uses onclick attributes on the button itself.
                // We should also ensure the form doesn't try to submit via its own AJAX.
                const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"], button#gform_submit_button_' + (form.id ? form.id.replace('gform_', '') : ''));
                submitters.forEach(btn => {
                    // Forcefully remove any existing onclick handlers that GF might have attached
                    if (btn.onclick) {
                        try { btn.onclick = null; } catch(e) {}
                    }
                    btn.addEventListener('click', function(ev){
                        ev.stopImmediatePropagation();
                        ev.preventDefault();
                        // Trigger our managed submit
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }, true);
                });
                
                // Block GF's iframe submission method
                if (form.getAttribute('target')) {
                    form.removeAttribute('target');
                }
                form.onsubmit = function(ev) { 
                    if (ev && ev.preventDefault) { ev.preventDefault(); }
                    return false; 
                };
            }

            // On static builds, for Fluent Forms specifically, intercept submit button clicks early (capture)
            if (isStatic && (form.classList.contains('frm-fluent-form') || (form.className && form.className.includes('frm-fluent-form')))) {
                const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitters.forEach(btn => {
                    btn.addEventListener('click', function(ev){
                        ev.stopImmediatePropagation();
                        ev.preventDefault();
                        // Trigger our managed submit
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }, true);
                });
            }

            // On static builds, for WPForms specifically, intercept submit button clicks early (capture)
            if (isStatic && (form.classList.contains('wpforms-form') || (form.className && form.className.includes('wpforms-form')))) {
                const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitters.forEach(btn => {
                    btn.addEventListener('click', function(ev){
                        ev.stopImmediatePropagation();
                        ev.preventDefault();
                        // Trigger our managed submit so our validator runs and we bypass WPForms AJAX handler
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }, true);
                });
            }

            // On static builds, for WS Form specifically, intercept submit button clicks early (capture)
            if (isStatic && (
                form.classList.contains('wsf-form') || (form.className && form.className.includes('wsf-form')) ||
                form.classList.contains('ws-form') || (form.className && form.className.includes('ws-form')) ||
                (form.closest && (form.closest('.wsf-form') || form.closest('.ws-form')))
            )) {
                const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitters.forEach(btn => {
                    btn.addEventListener('click', function(ev){
                        ev.stopImmediatePropagation();
                        ev.preventDefault();
                        // Trigger our managed submit so our validator runs and we bypass WS Form AJAX handler
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }, true);
                });
            }

            // On static builds, for Kadence Forms specifically, intercept submit button clicks early (capture)
            if (isStatic && (
                (form.closest && form.closest('.wp-block-kadence-form')) ||
                form.querySelector && form.querySelector('input[name="_kb_form_id"]')
            )) {
                const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitters.forEach(btn => {
                    btn.addEventListener('click', function(ev){
                        ev.stopImmediatePropagation();
                        ev.preventDefault();
                        // Trigger our managed submit so our validator runs and we bypass Kadence AJAX handler
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }, true);
                });
            }

            // On static builds, for Forminator specifically, intercept submit button clicks early (capture)
            if (isStatic && (form.classList.contains('forminator-custom-form') || (form.className && form.className.includes('forminator-custom-form')))) {
                const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitters.forEach(btn => {
                    btn.addEventListener('click', function(ev){
                        ev.stopImmediatePropagation();
                        ev.preventDefault();
                        // Trigger our managed submit to bypass Forminator AJAX
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }, true);
                });
            }

            form.addEventListener("submit", function (ev) {
                // Let validation run first (validation listener is in capture phase). We are in bubble.
                // If the form is invalid per HTML5 constraints, do not proceed; validator shows messages.
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                // Additionally, if our custom validator previously rendered any error messages, do not proceed.
                var hasCustomErrors = !!form.querySelector('.ssp-field-error') && Array.prototype.some.call(form.querySelectorAll('.ssp-field-error'), function(n){ return !!(n.textContent && n.textContent.trim()); });
                if (hasCustomErrors) { return; }
                
                // Prevent default submission and stop propagation
                ev.preventDefault();
                ev.stopImmediatePropagation();

                // Create a stable placeholder mount before the form so we can replace reliably later
                try {
                    if (!form.dataset || !form.dataset.sspMountId) {
                        var mount = document.createElement('div');
                        var mid = 'ssp-mount-' + Date.now() + '-' + Math.random().toString(36).slice(2);
                        mount.id = mid;
                        if (form.parentNode) {
                            form.parentNode.insertBefore(mount, form);
                            if (form.dataset) { form.dataset.sspMountId = mid; }
                        }
                    }
                } catch(e) { /* noop */ }

                // Build candidate ids for settings lookup depending on the plugin
                let candidates = [];

                if ((form.classList && form.classList.contains('wpcf7-form')) || (form.closest && form.closest('.wpcf7'))) {
                    // Contact Form 7
                    // Prefer the wrapper div with classes wpcf7 no-js (e.g., id="wpcf7-f107-p123-o1")
                    var cf7Wrap = form.closest('.wpcf7');
                    if (cf7Wrap && cf7Wrap.id) { candidates.push(cf7Wrap.id); }
                    // Hidden fields provided by CF7
                    var cf7Unit = form.querySelector('input[name="_wpcf7_unit_tag"]');
                    if (cf7Unit && cf7Unit.value) { candidates.push(cf7Unit.value); }
                    var cf7Hidden = form.querySelector('input[name="_wpcf7"]');
                    if (cf7Hidden && cf7Hidden.value) { candidates.push(cf7Hidden.value); }
                    // Fall back to the form's own id
                    if (form.id) { candidates.push(form.id); }
                } else if (form.className.includes('wpforms-form')) {
                    // WPForms
                    var wpfHidden = form.querySelector('input[name="wpforms[id]"]');
                    if (wpfHidden && wpfHidden.value) { candidates.push(wpfHidden.value); }
                    if (form.id) { candidates.push(form.id); }
                } else if (form.className.includes('wsf-form') || (form.closest && (form.closest('.wsf-form') || form.closest('.ws-form')))) {
                    // WS Form
                    // Hidden field
                    var wsfHidden = form.querySelector('input[name="wsf_form_id"]');
                    if (wsfHidden && wsfHidden.value) { candidates.push(wsfHidden.value); }
                    // Wrapper attributes
                    var wsfWrap = form.closest('.wsf-form') || form.closest('.ws-form');
                    if (wsfWrap) {
                        var did = wsfWrap.getAttribute('data-form-id') || wsfWrap.getAttribute('data-wsf-form-id');
                        if (did) { candidates.push(did); }
                        if (wsfWrap.id) { candidates.push(wsfWrap.id); }
                    }
                    // Fallback to form id
                    if (form.id) { candidates.push(form.id); }
                } else if ((form.parentNode && form.parentNode.className && form.parentNode.className.includes('gform_wrapper')) || form.className.includes('gform')) {
                    // Gravity Forms
                    var gfHidden = form.querySelector('input[name="gform_submit"]');
                    if (gfHidden && gfHidden.value) { candidates.push(gfHidden.value); }
                    // Wrapper IDs like gform_wrapper_1 or form IDs like gform_1
                    var gfWrap = (form.closest && form.closest('.gform_wrapper')) ? form.closest('.gform_wrapper') : null;
                    if (gfWrap && gfWrap.id) { candidates.push(gfWrap.id); }
                    if (form.id) { candidates.push(form.id); }
                    // Hidden identifiers used by GF
                    var gfUid = form.querySelector('input[name="gform_unique_id"]');
                    if (gfUid && gfUid.value) { candidates.push(gfUid.value); }
                    var gfTarget = form.querySelector('input[name^="gform_target_page_number_"]');
                    if (gfTarget && gfTarget.name) { candidates.push(gfTarget.name); }
                    var gfSource = form.querySelector('input[name^="gform_source_page_number_"]');
                    if (gfSource && gfSource.name) { candidates.push(gfSource.name); }
                } else if (form.className.includes('frm-fluent-form')) {
                    // Fluent Forms
                    var ffHidden = form.querySelector('input[name="_fluentform_id"]');
                    if (ffHidden && ffHidden.value) { candidates.push(ffHidden.value); }
                    if (form.getAttribute('data-form-id')) { candidates.push(form.getAttribute('data-form-id')); }
                    if (form.id) { candidates.push(form.id); }
                } else if (form.className.includes('elementor-form')) {
                    // Elementor Forms
                    var elHidden = form.querySelector("[name='form_id']");
                    if (elHidden && elHidden.value) { candidates.push(elHidden.value); }
                    if (form.id) { candidates.push(form.id); }
                } else if (form.classList.contains('forminator-custom-form') || (form.className && form.className.includes('forminator-custom-form'))) {
                    // Forminator
                    // Hidden field commonly present
                    var foHidden = form.querySelector('input[name="form_id"]');
                    if (foHidden && foHidden.value) { candidates.push(foHidden.value); }
                    // data-form-id attribute on the <form> element
                    var foDataId = form.getAttribute('data-form-id');
                    if (foDataId) { candidates.push(foDataId); }
                    // Wrapper and form ids like forminator-module-123 / forminator-form-123
                    if (form.id) { candidates.push(form.id); }
                    // Look upward for a wrapper whose id starts with forminator-module-
                    var foWrap = (form.closest && form.closest('[id^="forminator-module-"]'));
                    if (foWrap && foWrap.id) { candidates.push(foWrap.id); }
                } else if (form.className.includes('brxe-form') || form.className.includes('brxe-brf-pro-forms')) {
                    // Bricks
                    if (form.id) { candidates.push(form.id); }
                } else if (form.closest && (form.closest('.wp-block-kadence-form') || form.querySelector('input[name="_kb_form_id"]'))) {
                    // Kadence Forms: use only the hidden field _kb_form_id as the identifier
                    var kHidden = form.querySelector('input[name="_kb_form_id"]');
                    if (kHidden && kHidden.value) { candidates.push(kHidden.value); }
                } else {
                    if (form.id) { candidates.push(form.id); }
                }

                // Manage and submit form with the candidate ids.
                manageForm(config_url, candidates, form);
            }, false);
        });
    });
}
})();
