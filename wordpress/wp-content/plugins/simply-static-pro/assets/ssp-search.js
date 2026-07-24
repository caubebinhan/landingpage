'use strict';

const searchResults = [];

// Helper: render excerpt conditionally based on localized flag and presence
function renderExcerpt(item) {
    try {
        if (window.ssp_search && ssp_search.show_excerpt && item && item.excerpt) {
            return `<small>${item.excerpt}</small>`;
        }
    } catch (_) {}
    return '';
}

/**
 * Initialize Fuse.js search functionality.
 * This function is called when DOM is ready to ensure all meta tags are available.
 */
function initFuseSearch() {
    // Get index from JSON file - now safely inside DOM-ready
    let fuse_config_element = document.querySelector("meta[name='ssp-config-path']");

    if (null === fuse_config_element) {
        console.log('No Fuse.js config found.');
        return;
    }

    let config_path = fuse_config_element.getAttribute("content");
    let version_element = document.querySelector("meta[name='ssp-config-version']");
    let version_suffix = '';
    if (null !== version_element) {
        let v = version_element.getAttribute('content');
        if (v) {
            version_suffix = '?ver=' + encodeURIComponent(v);
        }
    }
    let index_url = window.location.origin + config_path + 'fuse-index.json' + version_suffix;
    let config_url = window.location.origin + config_path + 'fuse-config.json' + version_suffix;
    let index;
    let config;

    // Multilingual?
    let language = document.documentElement.lang.substring(0, 2);
    let is_multilingual = false;

    if (document.getElementsByTagName("link").length) {
        let links = document.getElementsByTagName("link");

        for (const link of links) {
            let language_tag = link.getAttribute("hreflang");

            if ('' !== language_tag && null !== language_tag) {
                is_multilingual = true;
            }
        }
    }


    async function loadConfig(callback) {

        try {
            const response = await fetch(config_url, {
                headers: {
                    "Content-Type": "application/json",
                }
            });
            if (!response.ok) {
                throw new Error(`Response status: ${response.status}`);
            }

            const json = await response.text();
            callback(json);

        } catch (error) {
            console.error(error.message);
        }


    }

    async function loadIndex(callback) {
        try {
            const response = await fetch(index_url, {
                headers: {
                    "Content-Type": "application/json",
                }
            });
            if (!response.ok) {
                throw new Error(`Response status: ${response.status}`);
            }

            const json = await response.text();
            callback(json);

        } catch (error) {
            console.error(error.message);
        }

    }

    // Track loading state to handle race condition between loadIndex and loadConfig
    let indexLoaded = false;
    let configLoaded = false;

    loadIndex(function (response) {
        let json = JSON.parse(response);
        const index = Object.values(json);

        // Build search index for Fuse.
        for (const value of index) {
            var result = {
                url: window.location.origin + value.path,
                title: value.title,
                excerpt: value.excerpt,
                content: value.content,
                language: value.language
            };

            if (is_multilingual) {
                // Include entry if:
                // 1. Entry has no language set (empty/undefined) - assume it's for all languages
                // 2. Entry's language matches the current document language
                if (!result.language || result.language === language) {
                    searchResults.push(result);
                }
            } else {
                searchResults.push(result);
            }
        }

        indexLoaded = true;

        // If fuse is already initialized (config loaded first), update its collection
        if (null !== fuse) {
            fuse.setCollection(searchResults);
        }
        
        // Notify that index data is now available
        try {
            window.dispatchEvent(new CustomEvent('ssp:index-ready'));
        } catch (_) {}
    });

// Search.

    let keys = ['title', 'content', 'excerpt', 'language'];
    let fuse = null;

    loadConfig(function (response) {
        config = JSON.parse(response);

        fuse = new Fuse(
            searchResults,
            {
                keys: keys,
                shouldSort: true,
                threshold: config.threshold ? config.threshold : 0.1,
                maxPatternLength: 50
            }
        );

        configLoaded = true;

        // If index already loaded (index loaded first), ensure fuse has the data
        if (indexLoaded && searchResults.length > 0) {
            fuse.setCollection(searchResults);
        }

        // Notify page helpers that Fuse is ready
        try {
            window.dispatchEvent(new CustomEvent('ssp:fuse-ready'));
        } catch (_) {
        }
        // Build selector-based instances (non search-page specific)
        try {
            maybeBuildSearch();
        } catch (_) {
        }
    });

    window.FuseSearchForm = function FuseSearchForm(el) {
        var self = this;
        let input = '';
        let results = [];
        let selected = -1;
        let showAutoComplete = false;
        let container = el;
        let searchFormNode = null;
        let searchInputNode = null;
        let autoCompleteNode = null;
        let resultNode = null;
        // Determine per-instance autocomplete allowance: always enabled now
        const allowAutoComplete = function() { return true; };

        this.handleSearchSubmit = function handleSearchSubmit(event) {
            if (event) {
                event.preventDefault()
            }

            input = searchInputNode.value.trim()
            selected = -1

            // If static results page is enabled, redirect to the static search results page (/__qs/)
            // But only if we're NOT already on the static search page
            if (input.length >= 1 && window.ssp_search && ssp_search.use_static_results_page && ssp_search.static_search_path) {
                // Check if we're already on the static search page to avoid redirect loop
                var staticPath = ssp_search.static_search_path;
                var basePath = staticPath.replace(/index\.html$/, '');
                if (basePath.charAt(basePath.length - 1) !== '/') {
                    basePath += '/';
                }
                // Normalize current path for comparison
                var currentPath = window.location.pathname;
                if (currentPath.length > 1 && currentPath.charAt(currentPath.length - 1) !== '/') {
                    currentPath += '/';
                }
                // Only redirect if we're NOT already on the search page
                var isOnSearchPage = currentPath === basePath || currentPath === staticPath || currentPath.endsWith('/__qs/') || currentPath.endsWith('/__qs/index.html');
                if (!isOnSearchPage) {
                    var searchUrl = window.location.origin + basePath + '?s=' + encodeURIComponent(input);
                    window.location.href = searchUrl;
                    return;
                }
                // If already on search page, fall through to render results inline
            }

            // Always compute results on submit so the results list can render
            if (input.length >= 3 && fuse) {
                results = fuse.search(input).slice(0, 7)
            }

            // Ensure autocomplete dropdown is (re)shown on submit
            showAutoComplete = true
            document.activeElement.blur()
            autoCompleteNode.innerHTML = self.renderAutoComplete()

            if (input.length > 2) {
                if (results.length) {
                    resultNode.innerHTML = `
                <div class="ssp-results"><h5>Searched for: <b>${input}</b></h5>
                <ul>
                  ${results.map((result, index) => `
                  <a href="${result.item.url}">
                    <li class='auto-complete-item${index === selected ? ' selected' : ''}'>
                      ${result.item.title}</br>
                        ${renderExcerpt(result.item)}
                    </li>
                  </a>
                `).join('')}
                </ul></div>`
                } else {
                    resultNode.innerHTML = `
            <div class="ssp-results">
            <h5>Searched for: <b>${input}</b></h5>
            <ul>
            <li>We couldn't find any matching results.</li>
            </ul>
            </div>`
                }
            } else {
                resultNode.innerHTML = '';
            }
        }

        this.renderAutoComplete = function renderAutoComplete() {
            if (!showAutoComplete || input.length < 3 || results.length === 0) {
                autoCompleteNode.classList.remove('show')
                return ''
            } else {
                autoCompleteNode.classList.add('show')
            }
            return `
                <ul>
                  ${results.map((result, index) => `
                  <a href="${result.item.url}">
                    <li class='auto-complete-item${index === selected ? ' selected' : ''}'>
                      ${result.item.title}</br>
                        ${renderExcerpt(result.item)}
                    </li>
                  </a>
                `).join('')}
                </ul>
              `
        }

        this.handleSearchInput = function handleSearchInput(event) {
            input = event.target.value
            results = []



            if (input.length >= 3) {
                if (fuse) {
                    results = fuse.search(input).slice(0, 7)
                } else {
                    // Fuse not ready yet; wait for it to load
                    results = []
                }
            }
            showAutoComplete = true
            autoCompleteNode.innerHTML = self.renderAutoComplete()
        }

        this.handleAutoCompleteClick = function handleAutoCompleteClick(event) {
            event.stopPropagation() // Prevent click from bubbling to window click handler
            searchInputNode.value = event.target.textContent.trim()
            showAutoComplete = false
            self.handleSearchSubmit()
        }


        this.init = function init() {
            searchFormNode = container.querySelector('.search-form');
            searchInputNode = container.querySelector('.search-input');
            autoCompleteNode = container.querySelector('.search-auto-complete');
            resultNode = container.querySelector('.result');

            if (!searchFormNode) {
                return;
            }

            // Make sure we remove such if it's registered before.
            searchFormNode.removeEventListener('submit', this.handleSearchSubmit)
            searchInputNode.removeEventListener('input', this.handleSearchInput)
            autoCompleteNode.removeEventListener('click', this.handleAutoCompleteClick)

            searchFormNode.addEventListener('submit', this.handleSearchSubmit)
            searchInputNode.addEventListener('input', this.handleSearchInput)
            autoCompleteNode.addEventListener('click', this.handleAutoCompleteClick)
            try { if (container && container.dataset) { container.dataset.sspFuseInit = '1'; } } catch(_) {}

            // If the input already has a value (e.g., from URL prefill), render suggestions immediately when Fuse is ready
            try {
                if (searchInputNode && searchInputNode.value && searchInputNode.value.trim().length >= 3) {
                    // Attempt immediate render; if Fuse not ready yet, ssp-search-page.js will trigger another input on fuse-ready
                    self.handleSearchInput({ target: searchInputNode });
                }
            } catch(_) {}
        }

        this.init();

        return this;
    }

    function handleWindowClick(event) {
        let autocompleters = document.querySelectorAll('.search-auto-complete');
        if (autocompleters.length) {
            autocompleters.forEach((autocompleteNode) => autocompleteNode.classList.remove('show'));
        }
    }


    function initSearch() {
        try {
            if (ssp_search.use_selector) {
                maybeBuildSearch();
            } else {
                // Initialize all existing Fuse forms on the page
                var allForms = document.querySelectorAll('.ssp-search');
                allForms.forEach(function(node){ new FuseSearchForm(node); });
            }
        } catch (e) {
        }
    }


    function maybeBuildSearch() {
        let builtAny = false;

        // Use config.selector from fuse-config.json, or fall back to ssp_search.custom_selector from localized JS
        let selectorSource = (config && config.selector) ? config.selector : null;
        if (!selectorSource && window.ssp_search && ssp_search.custom_selector) {
            selectorSource = ssp_search.custom_selector;
        }

        if (selectorSource) {
            const selectors = selectorSource.split(',').map(function (string) {
                return string.trim()
            }).filter(Boolean);

            for (let s = 0; s < selectors.length; s++) {
                let selector = selectors[s];

                if (!document.querySelectorAll(selector).length) {
                    continue;
                }

                let allSelectors = document.querySelectorAll(selector);

                for (let i = 0; i < allSelectors.length; i++) {
                    let node = allSelectors[i];
                    // Normalize to the nearest form so both Fuse and Algolia behave the same
                    let form = null;
                    if (node.tagName && node.tagName.toLowerCase() === 'form') {
                        form = node;
                    } else if (node.closest) {
                        form = node.closest('form');
                    }
                    if (!form) {
                        continue;
                    }
                    // Avoid double replacement
                    try { if (form.dataset && form.dataset.sspReplaced === '1') continue; } catch(_) {}
                    buildSearch(form);
                    builtAny = true;
                }
            }
        }

        // Fallback: if no custom selector elements were found/built,
        // initialize any existing .ssp-search elements (e.g., from shortcode)
        if (!builtAny) {
            var existingForms = document.querySelectorAll('.ssp-search');
            existingForms.forEach(function(node) {
                // Skip if already initialized
                try { if (node.dataset && node.dataset.sspFuseInit === '1') return; } catch(_) {}
                new FuseSearchForm(node);
            });
        }
    }

    function getRandomId() {
        var id = 'search' + Date.now() + (Math.random() * 100);

        if (document.getElementById(id)) {
            id = getRandomId();
        }

        return id;
    }

    function buildSearch(targetForm) {
        // Holder of search
        var div = document.createElement('div');
        // Random custom ID.
        var id = getRandomId();
        div.setAttribute('id', id);
        div.innerHTML = ssp_search.html;

        // Replace the form element with our unified markup
        targetForm.replaceWith(div);
        try { if (targetForm && targetForm.dataset) { targetForm.dataset.sspReplaced = '1'; } } catch(_) {}

        // Get it by ID to get the DOM element.
        var el = document.getElementById(id);
        var form = new FuseSearchForm(el);

        // After the form is fully rendered, populate the input and trigger search
        try {
            var finalize = function () {
                // No-op finalize: page-specific autofill and heading handling are done in ssp-search-page.js
                // We intentionally avoid synthetic submit/input here to keep this file Fuse-only.
            };
            if (typeof requestAnimationFrame === 'function') {
                requestAnimationFrame(finalize);
            } else {
                setTimeout(finalize, 0);
            }
        } catch (_) {
        }
    }

    // Initialize search when called (DOM is already ready at this point)
    initSearch();

    // Also re-initialize when Fuse config is loaded
    window.addEventListener('ssp:fuse-ready', function () {
        try {
            initSearch();
        } catch (_) {
        }
    });

    window.addEventListener('click', handleWindowClick);
}

// Execute when DOM is ready - this ensures meta tags are available regardless of script position
(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFuseSearch);
    } else {
        // DOM is already ready
        initFuseSearch();
    }
})();
