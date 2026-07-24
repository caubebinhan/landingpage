'use strict';

// Page helpers for the static search results page and global search redirects.
// This file intentionally contains only logic that is NOT core to Fuse itself.
// It depends on ssp-search.js (for localization and FuseSearchForm).

// --- Small utilities ---
function sspGetQueryParam(name) {
  try {
    const params = new URLSearchParams(window.location.search);
    const val = params.get(name);
    return val ? val : '';
  } catch (_) { return ''; }
}

// Derive the static export base path (e.g., '/static/') from the ssp-config-path meta tag only.
// Falls back to '/'. Always returns a leading and trailing slash.
function sspGetExportBase() {
  const marker = '/wp-content/';
  const norm = (b) => {
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
  } catch (_) {}
  return '/';
}

// Compute optional locale prefix accounting for the export base prefix.
function sspGetLocalePrefixConsideringBase(pathname) {
  const base = sspGetExportBase();
  const baseName = base.replace(/^\//, '').replace(/\/$/, '');
  const parts = pathname.split('/').filter(Boolean);
  let startIdx = 0;
  if (baseName && parts[0] === baseName) startIdx = 1;
  const locale = parts[startIdx] || '';
  return (locale && locale.length === 2) ? ('/' + locale + '/') : '/';
}

// Replace any literal word 'test' within common headings in a scope with the live term
function sspReplaceTestTokens(term, scopeEl) {
  try {
    if (!term) return;
    const selectors = ['h1','h2','h3','.h1','.h2','.h3','.page-title','.entry-title','.archive-title','.search-title','.wp-block-query-title'];
    
    // Helper function to replace text in nodes
    function replaceInScope(scope) {
      let replaced = false;
      selectors.forEach(function(sel){
        const nodes = scope.querySelectorAll(sel);
        nodes.forEach(function(n){
          if (n.querySelector && n.querySelector('#ssp-term')) return;
          n.childNodes.forEach(function(child){
            if (child.nodeType === Node.TEXT_NODE) {
              // Use a fresh regex for each replacement to avoid lastIndex issues
              const newText = child.textContent.replace(/\btest\b/gi, term);
              if (newText !== child.textContent) {
                child.textContent = newText;
                replaced = true;
              }
            }
          });
        });
      });
      return replaced;
    }
    
    // Try the provided scope or common content containers first
    const primaryScope = scopeEl || document.querySelector('main, #primary, .site-main, .content-area, .entry-content, .wp-block-post-content');
    if (primaryScope && replaceInScope(primaryScope)) {
      return; // Found and replaced in primary scope
    }
    
    // Fallback: search the entire document body (headings might be outside main content area)
    replaceInScope(document.body);
  } catch(_) {}
}

function sspSetHeadingTerm() {
  try {
    let term = sspGetQueryParam('s');
    if (!term) return;
    try { term = decodeURIComponent(term); } catch(_) {}
    term = (term + '').trim();
    const span = document.getElementById('ssp-term');
    if (span) {
      span.textContent = term;
    } else {
      sspReplaceTestTokens(term);
    }
  } catch (_) {}
}

function sspAddHideNativeStyles() {
  try {
    if (document.getElementById('ssp-search-hide-native')) return;
    const style = document.createElement('style');
    style.id = 'ssp-search-hide-native';
    style.type = 'text/css';
    // Hide only within common content containers to avoid blanking the entire page.
    style.textContent = [
      'main .search-results',
      'main .posts',
      'main .wp-block-query',
      '#primary .search-results',
      '.site-main .search-results',
      '.content-area .search-results',
      '.wp-block-post-content .search-results'
    ].join(',\n') + ' { display: none !important; }';
    document.head.appendChild(style);
  } catch (_) {}
}

// Ensure a heading placeholder exists above a given element
function sspEnsureHeadingPlaceholder(beforeEl) {
  try {
    if (document.getElementById('ssp-term')) return;
    const h = document.createElement('h1');
    h.className = 'ssp-search-heading';
    h.innerHTML = 'Searched for: <span id="ssp-term"></span>';
    if (beforeEl && beforeEl.parentNode) {
      beforeEl.parentNode.insertBefore(h, beforeEl);
    } else {
      const target = document.querySelector('main, #primary, .site-main, .content-area, .entry-content, .wp-block-post-content') || document.body;
      if (target.firstChild) target.insertBefore(h, target.firstChild); else target.appendChild(h);
    }
  } catch(_) {}
}

// Inject our search UI container into the detected target and initialize it
function sspInjectIntoSearchPage() {
  // If the page already has any ssp-search form, do not inject another one
  // But still run auto-populate and heading term logic
  var existingSearch = document.querySelector('.ssp-search');
  if (existingSearch || document.querySelector('.ssp-search-container-injected')) {
    // Still update heading and auto-populate for existing forms
    sspSetHeadingTerm();
    try {
      let term = sspGetQueryParam('s');
      if (term) {
        try { term = decodeURIComponent(term); } catch(_) {}
        term = (term + '').trim();
        // Populate ALL search inputs on the page, not just the first one
        const allInputs = document.querySelectorAll('.ssp-search .search-input');
        allInputs.forEach(function(inputEl) {
          if (inputEl) {
            inputEl.value = term;
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
          }
        });
      }
    } catch(_) {}
    return;
  }

  function firstMatch(selectors) {
    if (!selectors || !selectors.length) return null;
    for (let i = 0; i < selectors.length; i++) {
      const sel = selectors[i];
      try { const node = document.querySelector(sel); if (node) return node; } catch(_) {}
    }
    return null;
  }

  let candidates = [];
  if (window.ssp_search && ssp_search.custom_selector) candidates.push(ssp_search.custom_selector);
  if (window.ssp_search && Array.isArray(ssp_search.selectors)) candidates = candidates.concat(ssp_search.selectors);

  // Choose a safe content target; avoid falling back to <body> to prevent inserting above header/nav
  let target = firstMatch(candidates);
  if (!target) target = document.querySelector('main') || document.getElementById('primary');
  if (!target) return; // No safe target found; abort to avoid layout shifts

  const wrapper = document.createElement('div');
  wrapper.className = 'ssp-search-container-injected';
  // Insert generic shortcode HTML
  if (window.ssp_search && ssp_search.html) {
    wrapper.innerHTML = ssp_search.html;
  } else {
    wrapper.innerHTML = '';
  }

  const mode = (window.ssp_search && ssp_search.inject_mode) ? ssp_search.inject_mode : 'replace';
  if (mode === 'replace') {
    const hideSelectors = ['.search-results','.hfeed','.posts','.wp-block-query'];
    hideSelectors.forEach(function (sel) {
      try { const nodes = target.querySelectorAll(sel); nodes.forEach((n) => n.style.display = 'none'); } catch(_) {}
    });
    // Prefer placing our UI just before the first native results container if present
    let anchor = null;
    const nativeSelectors = ['.search-results','.wp-block-query','.posts'];
    for (let i = 0; i < nativeSelectors.length; i++) {
      try {
        const n = target.querySelector(nativeSelectors[i]);
        if (n) { anchor = n; break; }
      } catch(_) {}
    }
    if (anchor && anchor.parentNode) {
      anchor.parentNode.insertBefore(wrapper, anchor);
    } else {
      // Otherwise place at the end of the content target to stay below headers/navigation
      target.insertAdjacentElement('beforeend', wrapper);
    }
  } else if (mode === 'prepend') {
    // Prepend within the content scope (not the whole body) to minimize layout impact
    target.insertAdjacentElement('afterbegin', wrapper);
  } else {
    target.insertAdjacentElement('beforeend', wrapper);
  }

  // Ensure heading above our UI and fill with term
  sspEnsureHeadingPlaceholder(wrapper);
  sspSetHeadingTerm();

  // Tag the inner search as the dedicated search-page instance for targeting
  try {
    const innerSearch = wrapper.querySelector('.ssp-search');
    if (innerSearch) {
      innerSearch.classList.add('ssp-search--page');
    }
  } catch(_) {}

  // Initialize Fuse form behaviors from ssp-search.js on the actual page form node
  try {
    const pageFormNode = wrapper.querySelector('.ssp-search');
    if (pageFormNode) {
      new FuseSearchForm(pageFormNode);
    }
  } catch(_) {}

  // Auto-populate and trigger search
  try {
    const term = sspGetQueryParam('s');
    const inputEl = wrapper.querySelector('.search-input');
    if (term && inputEl) {
      inputEl.value = term;
      inputEl.dispatchEvent(new Event('input', { bubbles: true }));
      if (term.length >= 3) {
        const formEl = wrapper.querySelector('.search-form');
        if (formEl) formEl.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      }
    }
  } catch(_) {}
}

// Determine if current runtime is static and path equals the static search page path
function sspIsOnStaticSearchPage() {
  try {
    const isStatic = !!document.querySelector("meta[name='ssp-config-path']");
    if (!isStatic) return false;
    const staticPath = (window.ssp_search && ssp_search.static_search_path) ? ssp_search.static_search_path : '/__qs/index.html';
    const base = sspGetExportBase();
    const localePrefix = sspGetLocalePrefixConsideringBase(window.location.pathname);
    const expectedPath = (base.replace(/\/$/, '/') + (localePrefix === '/' ? '' : localePrefix.replace(/^\//, '')) + staticPath.replace(/^\//, ''));
    
    function norm(p){ if(!p) return '/'; if(p.charAt(0) !== '/') p = '/' + p; p = p.replace(/\/+/g,'/'); if(p.length>1 && p.endsWith('/')) p = p.slice(0,-1); return p; }
    
    // Check if paths are equivalent (handle /__qs/ vs /__qs/index.html)
    function pathsEquivalent(currentPath, expected) {
      const cur = norm(currentPath);
      const exp = norm(expected);
      if (cur === exp) return true;
      // If expected ends with /index.html, also match the directory path
      if (exp.endsWith('/index.html')) {
        const expDir = exp.slice(0, -('/index.html'.length));
        if (cur === expDir) return true;
      }
      return false;
    }
    
    return pathsEquivalent(window.location.pathname, expectedPath);
  } catch(_) { return false; }
}

// Redirect when ?s= is present
function sspMaybeRedirectForSearch() {
  try {
    const term = sspGetQueryParam('s');
    if (!term) return;

    // If we're already on the static search page, don't redirect
    if (sspIsOnStaticSearchPage()) return;

    // Static detection: prefer meta; fallback to export base being non-root
    let isStatic = !!document.querySelector("meta[name='ssp-config-path']");
    if (!isStatic) {
      const base = sspGetExportBase();
      if (base && base !== '/') isStatic = true;
    }
    if (!isStatic && window.ssp_search) {
      try {
        const wpSearchURL = new URL(ssp_search.search_url || '/?s=SSP_PLACEHOLDER', window.location.origin);
        if (wpSearchURL.host !== window.location.host) isStatic = true;
      } catch(_) {}
    }

    let target = null;
    if (isStatic) {
      const staticPath = (window.ssp_search && ssp_search.static_search_path) ? ssp_search.static_search_path : '/__qs/index.html';
      const base = sspGetExportBase();
      const localePrefix = sspGetLocalePrefixConsideringBase(window.location.pathname);
      let expectedPath = base.replace(/\/$/, '/') + (localePrefix === '/' ? '' : localePrefix.replace(/^\//, '')) + staticPath.replace(/^\/+/, '');
      if (expectedPath.charAt(0) !== '/') expectedPath = '/' + expectedPath;

      function pathsEquivalent(currentPath, expected) {
        function norm(p){ if(!p) return '/'; if (p.charAt(0) !== '/') p = '/' + p; p = p.replace(/\/+/g,'/'); if (p.length>1 && p.endsWith('/')) p = p.slice(0,-1); return p; }
        const cur = norm(currentPath); const exp = norm(expected);
        if (exp.endsWith('/index.html')) { const expDir = exp.slice(0, -('/index.html'.length)); if (cur === expDir) return true; }
        return cur === exp;
      }

      if (!pathsEquivalent(window.location.pathname, expectedPath)) {
        const url = new URL(expectedPath, window.location.origin);
        url.search = '';
        url.searchParams.set('s', term);
        target = url.toString();
      }
    } else if (window.ssp_search) {
      if (ssp_search.is_search === true) return; // already on search
      const base = ssp_search.search_url || (window.location.origin + '/?s=' + (ssp_search.search_placeholder || 'SSP_PLACEHOLDER'));
      const placeholder = ssp_search.search_placeholder || 'SSP_PLACEHOLDER';
      if (base.indexOf(placeholder) !== -1) {
        target = base.replace(placeholder, encodeURIComponent(term));
      } else {
        const url = new URL(base, window.location.origin);
        url.searchParams.set('s', term);
        target = url.toString();
      }
    }

    if (target && target !== window.location.href) {
      window.location.replace(target);
    }
  } catch(_) {}
}


function sspInitSearchPageIfNeeded() {
  try {
    if (!sspIsOnStaticSearchPage()) return;


    // Hide native results by CSS (non-destructive)
    sspAddHideNativeStyles();

    // Replace heading tokens early in case UI already present
    sspSetHeadingTerm();

    // Inject our UI if missing
    sspInjectIntoSearchPage();


    // Determine main content form
    function getMainForm() {
      var scope = document.querySelector('main, #primary, .site-main, .content-area, .entry-content, .wp-block-post-content') || document.body;
      return scope.querySelector('.ssp-search') || document.querySelector('.ssp-search');
    }

    // Wait helpers
    function waitFor(cond, timeoutMs, stepMs) {
      return new Promise(function(resolve){
        var t0 = Date.now();
        var step = stepMs || 50;
        var timeout = timeoutMs || 3000;
        (function tick(){
          try {
            if (cond()) { resolve(true); return; }
          } catch(_){ }
          if (Date.now() - t0 >= timeout) { resolve(false); return; }
          setTimeout(tick, step);
        })();
      });
    }

    function waitForFuseReady() {
      // Resolve on event or after small delay if already fired
      return new Promise(function(resolve){
        var done = false;
        function finish(){ if (!done){ done = true; resolve(true); } }
        // If ssp:fuse-ready has already fired, we still run after a microtask
        setTimeout(finish, 0);
        window.addEventListener('ssp:fuse-ready', finish, { once: true });
      });
    }

    function waitForFormInit(form) {
      return waitFor(function(){
        try { return form && form.dataset && form.dataset.sspFuseInit === '1'; } catch(_){ return false; }
      }, 3000, 50);
    }

    // Prefill the term into ALL search inputs with retry mechanism
    (function(){
      try {
        var term = sspGetQueryParam('s');
        try { term = decodeURIComponent(term || ''); } catch(_) {}
        term = (term + '').trim();
        if (!term) return;

        // Function to populate inputs and trigger autosuggest
        function populateInputs() {
          var allInputs = document.querySelectorAll('.ssp-search .search-input');
          var populated = false;
          allInputs.forEach(function(inp){
            try {
              inp.value = term;
              inp.dispatchEvent(new Event('input', { bubbles: true }));
              populated = true;
            } catch(_) {}
          });
          return populated && allInputs.length > 0;
        }

        // Try immediately
        populateInputs();

        // Retry with increasing delays to handle timing issues
        var retryDelays = [50, 100, 200, 500, 1000];
        retryDelays.forEach(function(delay){
          setTimeout(function(){
            var inputs = document.querySelectorAll('.ssp-search .search-input');
            inputs.forEach(function(inp){
              try {
                // Only set if empty or different
                if (!inp.value || inp.value !== term) {
                  inp.value = term;
                  inp.dispatchEvent(new Event('input', { bubbles: true }));
                }
              } catch(_) {}
            });
          }, delay);
        });

        // Also, after Fuse signals ready, trigger input again to ensure suggestions populate everywhere
        // Helper function to re-trigger inputs
        function retriggerInputs() {
          var inputs = document.querySelectorAll('.ssp-search .search-input');
          inputs.forEach(function(inp){
            try {
              // Ensure value is set
              if (!inp.value || inp.value !== term) {
                inp.value = term;
              }
              if ((inp.value || '').trim().length >= 3) {
                inp.dispatchEvent(new Event('input', { bubbles: true }));
              }
            } catch(_) {}
          });
        }
        
        window.addEventListener('ssp:fuse-ready', retriggerInputs, { once: true });
        // Also listen for index-ready which fires after searchResults are populated
        window.addEventListener('ssp:index-ready', retriggerInputs, { once: true });
      } catch(_){ }
    })();
  } catch(_) {}
}

// Wire up lifecycle
// Attach a submit handler that redirects to the static search page (__qs/index.html?s=term) on non-search pages only
function sspAttachSubmitRedirect(scopeEl) {
  try {
    if (sspIsOnStaticSearchPage()) return; // do not bind on the search page; Fuse handles submit rendering
    const root = scopeEl || document;
    const forms = root.querySelectorAll('.ssp-search .search-form');
    if (!forms || !forms.length) return;

    forms.forEach(function(form){
      if (form.dataset && form.dataset.sspSubmitBound === '1') return;
      form.addEventListener('submit', function(event){
        try {
          event.preventDefault();
          const input = form.querySelector('.search-input');
          let term = input ? (input.value || '') : '';
          term = (term + '').trim();
          if (!term) { if (input) input.focus(); return false; }

          const staticPath = (window.ssp_search && ssp_search.static_search_path) ? ssp_search.static_search_path : '/__qs/index.html';
          const base = sspGetExportBase();
          const localePrefix = sspGetLocalePrefixConsideringBase(window.location.pathname);
          let targetPath = base.replace(/\/$/, '/') + (localePrefix === '/' ? '' : localePrefix.replace(/^\//, '')) + staticPath.replace(/^\//, '');
          if (targetPath.charAt(0) !== '/') targetPath = '/' + targetPath;
          const url = new URL(targetPath, window.location.origin);
          url.search = '';
          url.searchParams.set('s', term);
          window.location.assign(url.toString());
          return false;
        } catch(_) {}
      }, true);
      if (form.dataset) form.dataset.sspSubmitBound = '1';
    });
  } catch (_) {}
}

(function(){
  // Initialize static search page after DOM is ready and after Fuse is ready
  function initWhenReady() {
    // Check for redirect AFTER DOM is ready so meta tags are available
    try { sspMaybeRedirectForSearch(); } catch(_) {}
    // Attach submit redirect globally (for pages with existing shortcode/UI)
    try { sspAttachSubmitRedirect(); } catch(_) {}
    // Init search page behaviors
    sspInitSearchPageIfNeeded();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWhenReady);
  } else {
    initWhenReady();
  }

  // Also wait for Fuse from ssp-search.js and re-run init (it is idempotent)
  window.addEventListener('ssp:fuse-ready', function(){
    try { sspInitSearchPageIfNeeded(); } catch(_) {}
    try { sspAttachSubmitRedirect(); } catch(_) {}
  });
})();
