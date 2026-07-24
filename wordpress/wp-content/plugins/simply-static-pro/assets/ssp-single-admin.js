'use strict';

// --------------------
// Shared utilities
// --------------------
var checkInterval = null;
var topDocument = (window.top && window.top.document) ? window.top.document : document;

function qs(selector, root) { return (root || document).querySelector(selector); }
function qsa(selector, root) { return Array.from((root || document).querySelectorAll(selector)); }

function setButtonsRunningState(isRunning) {
    var selectors = ['#generate-single', '.ssp-export-button', '.ssp-floating-export'];
    selectors.forEach(function(sel) {
        qsa(sel, document).forEach(function(btn) {
            if (isRunning) {
                btn.setAttribute('disabled', 'disabled');
                btn.classList.add('disabled');
            } else {
                btn.removeAttribute('disabled');
                btn.classList.remove('disabled');
            }
        });
        // Also mirror in topDocument (Elementor shell)
        if (topDocument !== document) {
            qsa(sel, topDocument).forEach(function(btn) {
                if (isRunning) {
                    btn.setAttribute('disabled', 'disabled');
                    btn.classList.add('disabled');
                } else {
                    btn.removeAttribute('disabled');
                    btn.classList.remove('disabled');
                }
            });
        }
    });
}

function checkIfRunning() {
    if (typeof ssp_single_ajax === 'undefined') {
        return false;
    }
    fetch(ssp_single_ajax.rest_url + 'simplystatic/v1/is-running', {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': ssp_single_ajax.rest_nonce
        }
    })
        .then(function(resp){ return resp.json(); })
        .then(function(resp){
            var json = (typeof resp === 'string') ? JSON.parse(resp) : resp;
            if (json && json.running) {
                setButtonsRunningState(true);
            } else {
                setButtonsRunningState(false);
                clearInterval(checkInterval);
            }
        })
        .catch(function(){ /* ignore */ });
}

function startCheckIfRunning() {
    checkIfRunning();
    checkInterval = setInterval(checkIfRunning, 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    startCheckIfRunning();

    // Hide main export if coming from single export
    try {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('type') === 'single_export') {
            var generate = qs('#generate'); if (generate) generate.style.display = 'none';
            qsa('.actions').forEach(function(a){ a.style.display = 'none'; });
        }
    } catch(e) {}

    // Utility: get current post ID across editors.
    function sspGetCurrentPostId() {
        try {
            if (window.wp && wp.data && wp.data.select) {
                var id = wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId && wp.data.select('core/editor').getCurrentPostId();
                if (id) return id;
            }
        } catch (e) {}
        var postIdEl = document.getElementById('post_ID');
        if (postIdEl && postIdEl.value) return postIdEl.value;
        var params = new URLSearchParams(window.location.search);
        var elId = params.get('post');
        if (elId) return elId;
        return null;
    }

    // Utility: get current post type across editors (Gutenberg/Classic/Elementor shells).
    function sspGetCurrentPostType() {
        try {
            // Gutenberg: use data store when available
            if (window.wp && wp.data && wp.data.select) {
                var st = wp.data.select('core/editor');
                if (st && st.getCurrentPostType) {
                    var t = st.getCurrentPostType();
                    if (t) return t;
                }
            }
        } catch (e) {}

        // Classic editor: hidden inputs
        try {
            var input = document.querySelector('input[name="post_type"], #post_type');
            if (input && input.value) return input.value;
        } catch (e) {}

        // Body class like post-type-page / post-type-ssp-form
        try {
            var body = document.body;
            if (body && body.classList) {
                for (var i = 0; i < body.classList.length; i++) {
                    var cls = body.classList[i];
                    if (cls.indexOf('post-type-') === 0) {
                        return cls.replace('post-type-', '');
                    }
                }
            }
        } catch (e) {}

        // Elementor: check topDocument body classes
        try {
            if (topDocument && topDocument.body && topDocument.body.classList) {
                for (var j = 0; j < topDocument.body.classList.length; j++) {
                    var c = topDocument.body.classList[j];
                    if (c.indexOf('post-type-') === 0) {
                        return c.replace('post-type-', '');
                    }
                }
            }
        } catch (e) {}

        // URL param fallback
        try {
            var params = new URLSearchParams(window.location.search);
            var pt = params.get('post_type');
            if (pt) return pt;
        } catch (e) {}

        return null;
    }

    function restoreButton(buttonEl) {
        buttonEl.removeAttribute('disabled');
        buttonEl.classList.remove('disabled');
        buttonEl.setAttribute('aria-busy', 'false');
        if (buttonEl.dataset && buttonEl.dataset.sspOriginalHtml !== undefined) {
            buttonEl.innerHTML = buttonEl.dataset.sspOriginalHtml;
        }
    }

    function sspStartSingleExport(buttonEl) {
        // Do not allow single export on our internal ssp-form post type
        try {
            var currentType = sspGetCurrentPostType();
            if (currentType === 'ssp-form') {
                console.warn('Simply Static Pro: Single Export is not available for ssp-form post type.');
                return;
            }
        } catch(e) {}
        var single_id = sspGetCurrentPostId() || buttonEl.getAttribute('data-id');
        if (!single_id) {
            console.warn('Simply Static Pro: could not determine post ID for single export.');
            return;
        }

        buttonEl.setAttribute('disabled', 'disabled');
        buttonEl.classList.add('disabled');
        buttonEl.setAttribute('aria-busy', 'true');
        if (!buttonEl.dataset.sspOriginalHtml) {
            buttonEl.dataset.sspOriginalHtml = buttonEl.innerHTML;
        }
        buttonEl.textContent = 'Exporting…';

        var body = new URLSearchParams();
        body.set('action', 'apply_single');
        body.set('nonce', ssp_single_ajax.single_nonce);
        body.set('single_id', String(single_id));

        fetch(ssp_single_ajax.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function(res){ return res.json(); })
            .then(function(){ restoreButton(buttonEl); })
            .catch(function(){ restoreButton(buttonEl); });
    }

    // Legacy metabox button support (delegated)
    document.addEventListener('click', function(e){
        if (e.target && e.target.matches('#generate-single')) {
            e.preventDefault();
            sspStartSingleExport(e.target);
        }
    });

    // Inject Export button into Gutenberg header next to Publish/Update.
    function injectGutenbergButton() {
        var candidates = [
            '.edit-post-header__settings',
            '.edit-post-header__toolbar',
            '.edit-post-header .edit-post-header__toolbar',
            '.interface-interface-skeleton__header .edit-post-header__settings',
            '.interface-interface-skeleton__header .interface-pinned-items',
            '.interface-interface-skeleton__header .interface-interface-skeleton__actions'
        ];
        var container = null;
        for (var i = 0; i < candidates.length; i++) {
            var el = qs(candidates[i]);
            if (el) { container = el; break; }
        }
        if (!container) return false;
        if (qs('.ssp-export-button', container)) return true;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'components-button is-primary ssp-export-button';
        btn.textContent = 'Export';
        btn.addEventListener('click', function(){ sspStartSingleExport(btn); });
        container.insertBefore(btn, container.firstChild);
        return true;
    }

    // Inject Export button into Classic Editor near Publish/Update.
    function injectClassicButton() {
        var actions = qs('#major-publishing-actions');
        if (!actions) return false;
        // Skip for ssp-form post type
        try { if (sspGetCurrentPostType() === 'ssp-form') return false; } catch(e) {}
        if (qs('.ssp-export-button', actions)) return true;
        var publish = qs('#publish', actions);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'button ssp-export-button';
        btn.style.marginLeft = '8px';
        btn.textContent = 'Export';
        btn.addEventListener('click', function(){ sspStartSingleExport(btn); });
        if (publish) {
            publish.insertAdjacentElement('afterend', btn);
        } else {
            actions.appendChild(btn);
        }
        return true;
    }

    // Note: Elementor toolbar/footer integrations removed; we now rely solely on a floating button in Elementor.

    // Ensure brand styling via CSS if not present.
    function ensureBrandStyles() {
        if (topDocument.getElementById('ssp-export-style') || document.getElementById('ssp-export-style')) return;
        var css = '.ssp-export-button{background-color:#6804cc!important;border-color:#6804cc!important;color:#fff!important}.ssp-export-button.disabled{opacity:.7;cursor:not-allowed}';
        var style = topDocument.createElement('style');
        style.id = 'ssp-export-style';
        style.appendChild(document.createTextNode(css));
        (topDocument.head || document.head).appendChild(style);
    }

    ensureBrandStyles();

    // Elementor: floating bottom-right button
    (function(){
        var STYLE_ID = 'ssp-floating-export-style';
        function ensureFloatingStyles(doc){
            try {
                doc = doc || topDocument || document;
                if (doc.getElementById(STYLE_ID)) return;
                var css = ''+
                    '.ssp-floating-export{position:fixed;right:20px;bottom:20px;z-index:10010;background:#6804cc;color:#fff;border:0;border-radius:28px;padding:10px 16px;font-weight:600;line-height:1;cursor:pointer;box-shadow:0 6px 18px rgba(0,0,0,.15);}'+
                    '.ssp-floating-export:hover{filter:brightness(1.05);}'+
                    '.ssp-floating-export:active{transform:translateY(1px);}'+
                    '.ssp-floating-export[disabled], .ssp-floating-export.disabled{opacity:.7;cursor:not-allowed;}'+
                    '@media (max-width: 782px){ .ssp-floating-export{ right:14px; bottom:14px; } }';
                var style = doc.createElement('style');
                style.id = STYLE_ID;
                style.appendChild(document.createTextNode(css));
                (doc.head || document.documentElement).appendChild(style);
            } catch(e) {}
        }
        function isElementorEditorContext(){
            // Only treat as Elementor when the current top window explicitly has action=elementor
            // This avoids false-positives on regular post editor screens where Elementor scripts
            // (e.g., elementorCommon) might be present globally.
            try {
                var search = (window.top && window.top.location && window.top.location.search) ? window.top.location.search : window.location.search;
                var params = new URLSearchParams(search || '');
                if (params.get('action') === 'elementor') return true;
            } catch(e) {}
            return false;
        }
        function createFloatingButton(){
            if (topDocument.querySelector('.ssp-floating-export')) return null;
            var btn = topDocument.createElement('button');
            btn.type = 'button';
            btn.className = 'ssp-floating-export';
            btn.setAttribute('aria-label', 'Simply Static Export');
            btn.textContent = 'Export';
            btn.addEventListener('click', function(){ sspStartSingleExport(btn); });
            return btn;
        }
        function injectFloatingButton(){
            if (!isElementorEditorContext()) return false;
            // Skip for ssp-form post type
            try { if (sspGetCurrentPostType() === 'ssp-form') return false; } catch(e) {}
            if (typeof ssp_single_ajax !== 'undefined' && String(ssp_single_ajax.can_export) === '0') return false;
            ensureFloatingStyles();
            var existing = topDocument.querySelector('.ssp-floating-export');
            if (existing) return true;
            var btn = createFloatingButton();
            if (!btn) return true;
            try { (topDocument.body || document.body).appendChild(btn); return true; } catch(e) { return false; }
        }
        function removeFloatingButton(){
            try {
                var el = topDocument.querySelector('.ssp-floating-export');
                if (el && !isElementorEditorContext()) { el.parentNode.removeChild(el); }
            } catch(e) {}
        }
        function setupFloatingLifecycle(){
            injectFloatingButton();
            try {
                if (window.elementor && elementor.on) {
                    elementor.on('editor:loaded', function(){ setTimeout(injectFloatingButton, 50); });
                    elementor.on('document:loaded', function(){ setTimeout(injectFloatingButton, 50); });
                    elementor.on('preview:loaded', function(){ setTimeout(injectFloatingButton, 50); });
                }
            } catch(e) {}
            try {
                if (window.elementor && elementor.channels && elementor.channels.editor && elementor.channels.editor.on) {
                    elementor.channels.editor.on('change:status change:page:settings', function(){ setTimeout(injectFloatingButton, 50); });
                }
            } catch(e) {}
            try {
                var obs = new MutationObserver(function(){ injectFloatingButton(); });
                obs.observe(topDocument.body || document.body, { childList: true, subtree: true });
            } catch(e) {}
            try { (window.top || window).addEventListener('focus', function(){ setTimeout(injectFloatingButton, 50); }); } catch(e) {}
        }
        if (window.requestIdleCallback) { requestIdleCallback(setupFloatingLifecycle); } else { setTimeout(setupFloatingLifecycle, 200); }
        window.addEventListener('beforeunload', removeFloatingButton);
    })();

    // Note: Elementor top bar and footer hook integrations have been removed.

    function tryInjectAll() {
        // Only inject into Gutenberg and Classic editors.
        // Skip for ssp-form post type entirely
        try { if (sspGetCurrentPostType() === 'ssp-form') return; } catch(e) {}
        injectGutenbergButton();
        injectClassicButton();
        // Elementor is handled exclusively by the floating button now.
    }
    tryInjectAll();
    if (window.requestIdleCallback) { requestIdleCallback(function(){ tryInjectAll(); }); } else { setTimeout(function(){ tryInjectAll(); }, 250); }
    try { var injectObserver = new MutationObserver(function () { tryInjectAll(); }); injectObserver.observe(document.body, { childList: true, subtree: true }); } catch(e) {}
});