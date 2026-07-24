'use strict';

// Helper: render excerpt conditionally based on localized flag and presence (parity with Fuse)
function sspAlgoliaRenderExcerpt(item) {
    try {
        if (window.ssp_search && ssp_search.show_excerpt && item && item.excerpt) {
            return `<small>${item.excerpt}</small>`;
        }
    } catch (_) {}
    return '';
}

(function(){
    // Helper: derive the static export base (e.g., '/static/') from the meta tag
    function sspGetExportBaseAlgolia() {
        const marker = '/wp-content/';
        const norm = function(b){
            if (!b) return '/';
            if (b.charAt(0) !== '/') b = '/' + b;
            b = b.replace(/\/+/g, '/');
            if (!b.endsWith('/')) b += '/';
            return b;
        };
        try {
            const meta = document.querySelector("meta[name='ssp-config-path']");
            if (meta) {
                let p = meta.getAttribute('content') || '/';
                if (p.charAt(0) !== '/') p = '/' + p;
                const idx = p.indexOf(marker);
                if (idx !== -1) {
                    const base = p.substring(0, idx + 1);
                    return norm(base || '/');
                }
            }
        } catch(_) {}
        return '/';
    }
    let configEl = document.querySelector("meta[name='ssp-config-path']");
    let configPath = '/';
    let configUrl = '';
    if (configEl) {
        configPath = configEl.getAttribute('content') || '/';
        const versionEl = document.querySelector("meta[name='ssp-config-version']");
        let ver = '';
        if (versionEl) {
            const v = versionEl.getAttribute('content');
            if (v) ver = '?ver=' + encodeURIComponent(v);
        }
        configUrl = window.location.origin + configPath + 'algolia.json' + ver;
    }

    // Multilingual?
    const language = (document.documentElement.lang || '').substring(0, 2);
    let isMultilingual = false;
    try {
        const links = document.getElementsByTagName('link');
        for (const link of links) {
            const tag = link.getAttribute('hreflang');
            if (tag) { isMultilingual = true; break; }
        }
    } catch(_) {}

    async function fetchJSON(url) {
        const res = await fetch(url, { headers: { 'Content-Type': 'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    async function getConfig() {
        // 1) Try remote config if we have a URL
        if (configUrl) {
            try {
                return await fetchJSON(configUrl);
            } catch (e) {
                console.warn('Algolia config json not available, falling back to localized config:', e);
            }
        }
        // 2) Fallback to localized config
        try {
            if (window.ssp_search && window.ssp_search.algolia_config) {
                return window.ssp_search.algolia_config;
            }
        } catch(_) {}
        return null;
    }

    (async function init(){
        let cfg = await getConfig();
        if (!cfg || !cfg.app_id || !cfg.api_key || !cfg.index) {
            console.error('Algolia configuration missing. Ensure algolia.json or localized config is available.');
            return;
        }

        let client;
        let index;
        try {
            client = algoliasearch(cfg.app_id, cfg.api_key);
            index = client.initIndex(cfg.index);
        } catch(e) {
            console.error('Algolia init failed:', e);
            return;
        }

        // Build controller similar to FuseSearchForm but powered by Algolia
        window.AlgoliaSearchForm = function AlgoliaSearchForm(container) {
            const self = this;
            let input = '';
            let results = [];
            let selected = -1;
            let showAutoComplete = false;
            let searchFormNode = null;
            let searchInputNode = null;
            let autoCompleteNode = null;
            let resultNode = null;
            let lastQuery = '';

            this.renderAutoComplete = function() {
                if (!showAutoComplete || input.length < 3 || results.length === 0) {
                    if (autoCompleteNode) autoCompleteNode.classList.remove('show');
                    return '';
                } else {
                    autoCompleteNode.classList.add('show');
                }
                return `
                <ul>
                  ${results.map((item, index) => `
                  <a href="${item.url}">
                    <li class='auto-complete-item${index === selected ? ' selected' : ''}'>
                      ${item.title}</br>
                        ${sspAlgoliaRenderExcerpt(item)}
                    </li>
                  </a>
                `).join('')}
                </ul>
              `;
            };

            function mapHit(hit) {
                return {
                    url: window.location.origin + (hit.path || hit.url || ''),
                    title: hit.title || '',
                    excerpt: hit.excerpt || ''
                };
            }

            async function performSearch(q) {
                lastQuery = q;
                try {
                    const res = await index.search(q, { hitsPerPage: 7 });
                    // guard against out-of-order responses
                    if (lastQuery !== q) return;
                    let hits = res && res.hits ? res.hits : [];
                    if (isMultilingual) {
                        hits = hits.filter(h => (h.language || '').substring(0,2) === language);
                    }
                    results = hits.map(mapHit).filter(r => r.title);
                    autoCompleteNode.innerHTML = self.renderAutoComplete();
                } catch(e) {
                    console.error('Algolia search error:', e);
                }
            }

            this.handleSearchInput = function(event) {
                input = (event && event.target ? event.target.value : (searchInputNode ? searchInputNode.value : '') ) || '';
                results = [];
                if (input.length >= 3) {
                    showAutoComplete = true;
                    performSearch(input);
                } else {
                    showAutoComplete = false;
                    autoCompleteNode.innerHTML = '';
                    autoCompleteNode.classList.remove('show');
                }
            };

            this.handleSearchSubmit = function(event) {
                // Always redirect to the search results page (no inline results under the form)
                if (event) event.preventDefault();
                input = (searchInputNode ? (searchInputNode.value || '') : '').trim();
                selected = -1;

                // If empty, do nothing
                if (!input) {
                    return;
                }

                // If static results page is enabled, prefer the static export endpoint under __qs
                var useStaticPage = true;
                try { useStaticPage = !!(window.ssp_search && ssp_search.use_static_results_page); } catch(_) {}

                if (useStaticPage) {
                    // Preferred: redirect to static export query endpoint: <exportBase>__qs/?s=<term>
                    try {
                        var base = sspGetExportBaseAlgolia();
                        var staticUrl = window.location.origin + base + '__qs/?s=' + encodeURIComponent(input);
                        window.location.href = staticUrl;
                        return;
                    } catch(_) {}
                }

                // Fallback or when static page disabled: use localized search URL template if available
                try {
                    var template = (window.ssp_search && ssp_search.search_url) ? ssp_search.search_url : (window.location.origin + '/?s=SSP_PLACEHOLDER');
                    var url = template.replace('SSP_PLACEHOLDER', encodeURIComponent(input));
                    window.location.href = url;
                } catch(_) {
                    // Hard fallback
                    window.location.href = '/?s=' + encodeURIComponent(input);
                }
            };

            this.handleAutoCompleteClick = function(event) {
                try { event.stopPropagation(); } catch(_) {}
                if (!event) return;
                const target = event.target;
                if (!target) return;
                const text = (target.textContent || '').trim();
                if (searchInputNode) searchInputNode.value = text;
                showAutoComplete = false;
                self.handleSearchSubmit();
            };

            this.init = function(){
                searchFormNode = container.querySelector('.search-form');
                searchInputNode = container.querySelector('.search-input');
                autoCompleteNode = container.querySelector('.search-auto-complete');
                resultNode = container.querySelector('.result');
                if (!searchFormNode) return;

                // Remove previous listeners if any
                searchFormNode.removeEventListener('submit', this.handleSearchSubmit);
                searchInputNode.removeEventListener('input', this.handleSearchInput);
                autoCompleteNode.removeEventListener('click', this.handleAutoCompleteClick);

                searchFormNode.addEventListener('submit', this.handleSearchSubmit);
                searchInputNode.addEventListener('input', this.handleSearchInput);
                autoCompleteNode.addEventListener('click', this.handleAutoCompleteClick);
                try { if (container && container.dataset) { container.dataset.sspAlgoliaInit = '1'; } } catch(_) {}

                // If the input already has a value (e.g. on search page), render suggestions immediately
                try {
                    if (searchInputNode && searchInputNode.value && searchInputNode.value.trim().length >= 3) {
                        self.handleSearchInput({ target: searchInputNode });
                    }
                } catch(_) {}
            };

            this.init();
            return this;
        };

        // Build instances for any rendered shortcode blocks
        function buildInstances() {
            try {
                const nodes = document.querySelectorAll('.ssp-search');
                nodes.forEach(function(n){
                    try { if (n.dataset && (n.dataset.sspAlgoliaInit === '1' || n.dataset.sspFuseInit === '1')) return; } catch(_) {}
                    try { new window.AlgoliaSearchForm(n); } catch(_) {}
                });
            } catch(_) {}
        }

        // Selector-based injection (unified approach):
        // We prefer selectors that point to a <form>. If a selector targets an input or wrapper,
        // we normalize to its closest('form') so both integrations behave consistently.
        function maybeInjectBySelector() {
            try {
                // Prefer selector coming from cfg (algolia.json); fallback to localized one
                const raw = (cfg && cfg.selector) ? cfg.selector : (window.ssp_search && ssp_search.custom_selector ? ssp_search.custom_selector : '');
                if (!raw) return;

                // Split on commas, trim whitespace
                const sels = raw.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                if (!sels.length) return;

                sels.forEach(function(sel){
                    try {
                        const matches = document.querySelectorAll(sel);
                        if (!matches || !matches.length) return;

                        matches.forEach(function(node){
                            try {
                                // Normalize to form element
                                let form = null;
                                if (node.tagName && node.tagName.toLowerCase() === 'form') {
                                    form = node;
                                } else if (node.closest) {
                                    form = node.closest('form');
                                }
                                if (!form) return; // cannot normalize, skip

                                // Avoid double replacement if we've already initialized on this form
                                try { if (form.dataset && (form.dataset.sspReplaced === '1')) return; } catch(_) {}

                                // Create a holder and replace the form with unified markup
                                const holder = document.createElement('div');
                                const id = 'ssp-search-' + Date.now() + '-' + Math.floor(Math.random()*100000);
                                holder.setAttribute('id', id);
                                holder.innerHTML = (window.ssp_search && ssp_search.html) ? ssp_search.html : '';
                                // Replace the form in DOM
                                form.replaceWith(holder);
                                try { form.dataset.sspReplaced = '1'; } catch(_) {}

                                // Initialize controller on the new container
                                const el = document.getElementById(id);
                                if (el) {
                                    try { new window.AlgoliaSearchForm(el); } catch(_) {}
                                }
                            } catch(_) {}
                        });
                    } catch(_) {}
                });
            } catch(_) {}
        }

        // Fire a generic-ready event used by page helper (reuse fuse event name for parity)
        try { window.dispatchEvent(new CustomEvent('ssp:fuse-ready')); } catch(_) {}

        // Helper: on search results page, prefill the input with `s` and trigger suggestions
        function prefillAndTriggerIfSearchPage() {
            try {
                if (!(window.ssp_search && ssp_search.is_search)) return;
                // Read term from query string
                var term = '';
                try {
                    var params = new URLSearchParams(window.location.search);
                    term = params.get('s') || '';
                } catch(_) {}
                try { term = decodeURIComponent(term || ''); } catch(_) {}
                term = (term + '').trim();
                if (!term) return;

                // Fill every unified search input and trigger input to render autosuggest
                var inputs = document.querySelectorAll('.ssp-search .search-input');
                if (!inputs || !inputs.length) return;
                inputs.forEach(function(inp){
                    try { inp.value = term; } catch(_) {}
                });
                // Trigger input so Algolia controller performs search (min 3 chars)
                inputs.forEach(function(inp){
                    try { inp.dispatchEvent(new Event('input', { bubbles: true })); } catch(_) {}
                });
            } catch(_) {}
        }

        // Build now and also on DOMContentLoaded
        function bootAll() {
            // If a selector is configured, perform selector-based injection first
            try {
                if (window.ssp_search && ssp_search.use_selector) {
                    maybeInjectBySelector();
                }
            } catch(_) {}
            // Then initialize any already-rendered shortcode instances
            buildInstances();

            // If we are on the static search results page, prefill & trigger autosuggest
            prefillAndTriggerIfSearchPage();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootAll);
        } else {
            bootAll();
        }

        // Also, after our generic ready event (which we already fired), attempt again
        try {
            window.addEventListener('ssp:fuse-ready', function(){
                prefillAndTriggerIfSearchPage();
            }, { once: true });
        } catch(_) {}
    })();
})();

