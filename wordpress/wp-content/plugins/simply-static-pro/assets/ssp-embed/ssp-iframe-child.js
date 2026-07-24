/*
 * Simply Static Pro – Responsive iframe child script
 * Runs inside the ssp-form page (WordPress domain). It measures the page height
 * and posts it to the parent window so the embedding iframe can resize.
 */
(function () {
  function getQueryParam(name) {
    try {
      var m = new RegExp('[?&]' + name + '=([^&#]*)').exec(window.location.search);
      return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
    } catch (e) { return ''; }
  }

  var iframeId = getQueryParam('ssp_iframe_id') || '';

  var pending = false;
  function measure() {
    var body = document.body || null;
    var html = document.documentElement || null;
    var se = document.scrollingElement || html || body;
    // Use multiple strategies and pick the largest reasonable value
    var h1 = body ? body.scrollHeight : 0;
    var h2 = html ? html.scrollHeight : 0;
    var h3 = body ? body.offsetHeight : 0;
    var h4 = html ? html.offsetHeight : 0;
    var h5 = html ? Math.ceil(html.getBoundingClientRect().height) : 0;
    var h6 = body ? Math.ceil(body.getBoundingClientRect().height) : 0;
    var h7 = se ? se.scrollHeight : 0;
    var height = Math.max(h1, h2, h3, h4, h5, h6, h7);
    // Guard against 0/NaN
    if (!height || height < 0) {
      height = Math.max(h3, h4, 1);
    }
    // Add a small buffer to avoid bottom clipping due to margins/borders
    return height + 24;
  }

  function postHeight() {
    try {
      var msg = {
        type: 'SSP_FORM_HEIGHT',
        id: iframeId,
        height: measure()
      };
      window.parent && window.parent.postMessage(msg, '*');
    } catch (e) {}
  }

  function schedule() {
    if (pending) return;
    pending = true;
    setTimeout(function(){ pending = false; postHeight(); }, 0);
  }

  // Respond to explicit pings from the host
  window.addEventListener('message', function (evt) {
    try {
      var data = evt.data;
      if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch (_) {}
      }
      if (!data || !data.type) return;
      if (data.type === 'SSP_PING') {
        // If an id is specified and it doesn't match, still respond — host may match by contentWindow
        schedule();
      }
    } catch (e) {}
  });

  // Kick off on DOM ready and after load (assets may change height)
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    schedule();
  } else {
    document.addEventListener('DOMContentLoaded', schedule);
  }
  window.addEventListener('load', function () {
    // Burst a few times to catch late layout shifts
    var i = 0;
    var burst = setInterval(function(){
      schedule();
      if (++i >= 5) clearInterval(burst);
    }, 150);

    // Additional rAF burst to capture font/image reflow
    var rafCount = 0;
    function rafBurst(){
      schedule();
      if (++rafCount < 12) {
        requestAnimationFrame(rafBurst);
      }
    }
    if (typeof requestAnimationFrame !== 'undefined') {
      requestAnimationFrame(rafBurst);
    }
  });
  window.addEventListener('resize', schedule);

  // Watch for DOM/layout changes (form validation messages, conditional fields, etc.)
  if (typeof ResizeObserver !== 'undefined') {
    try {
      var ro = new ResizeObserver(function () { schedule(); });
      ro.observe(document.documentElement);
    } catch (e) {}
  } else if (typeof MutationObserver !== 'undefined') {
    try {
      var mo = new MutationObserver(function () { schedule(); });
      mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true });
    } catch (e) {}
  } else {
    // Fallback periodic
    setInterval(schedule, 500);
  }
})();
