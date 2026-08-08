{{-- EcomSolveBD browser collect (Woo-parity fields: 6-char user_id, utm, click_ids, first_touch). --}}
@php
  $apiBase = rtrim((string) config('ecomsolvebd.api_base', 'https://api.ecomsolvebd.com'), '/');
  $merchantKey = (string) config('ecomsolvebd.merchant_key', '');
  $deployEnv = trim((string) config('ecomsolvebd.deploy_env', ''));
@endphp
@if($merchantKey !== '')
<script>
(function () {
  var API = @json($apiBase);
  var KEY = @json($merchantKey);
  var DEPLOY = @json($deployEnv);
  var CHARSET = '0123456789abcdefghijklmnopqrstuvwxyz';
  var FT_KEY = 'esb_first_touch';

  function genShortId(len) {
    len = len || 6;
    var out = '';
    try {
      var bytes = new Uint8Array(len);
      crypto.getRandomValues(bytes);
      for (var i = 0; i < len; i++) out += CHARSET[bytes[i] % CHARSET.length];
      return out;
    } catch (e) {
      return (Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2))
        .replace(/[^a-z0-9]/g, '')
        .slice(0, len);
    }
  }

  function persistVid(v) {
    try {
      localStorage.setItem('esb_vid', v);
    } catch (e) {}
    try {
      document.cookie =
        'esb_vid=' + encodeURIComponent(v) + ';path=/;max-age=63072000;SameSite=Lax';
    } catch (e) {}
  }

  /** Woo parity: 5–8 char [a-z0-9]. Keep existing valid short ids; migrate oversized ids once. */
  function vid() {
    try {
      var v = localStorage.getItem('esb_vid') || '';
      if (!v) {
        var m = document.cookie.match(/(?:^|;\s*)esb_vid=([^;]+)/);
        if (m && m[1]) {
          try {
            v = decodeURIComponent(m[1]);
          } catch (e) {
            v = m[1];
          }
        }
      }
      v = String(v || '').toLowerCase();
      if (/^[a-z0-9]{5,8}$/.test(v)) {
        persistVid(v);
        return v;
      }
      v = genShortId(6);
      persistVid(v);
      return v;
    } catch (e) {
      return genShortId(6);
    }
  }

  function captureFromHref(href) {
    var clickIds = {};
    var utm = {};
    try {
      var u = new URL(href);
      ['fbclid', 'gclid', 'ttclid', 'wbraid', 'gbraid', 'msclkid'].forEach(function (k) {
        var v = u.searchParams.get(k);
        if (v) clickIds[k] = v;
      });
      ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (k) {
        var v = u.searchParams.get(k);
        if (v) utm[k] = v;
      });
    } catch (e) {}
    return { clickIds: clickIds, utm: utm };
  }

  function ensureFirstTouch(snap) {
    try {
      var raw = localStorage.getItem(FT_KEY);
      if (raw) {
        try {
          return JSON.parse(raw);
        } catch (e) {
          return snap;
        }
      }
      localStorage.setItem(FT_KEY, JSON.stringify(snap));
      return snap;
    } catch (e) {
      return snap;
    }
  }

  function send(eventName, extra) {
    var now = captureFromHref(location.href);
    var first = ensureFirstTouch({
      occurredAt: new Date().toISOString(),
      landingUrl: location.href,
      url: location.href,
      clickIds: now.clickIds,
      utm: now.utm,
    });
    var body = Object.assign(
      {
        event: eventName,
        timestamp: new Date().toISOString(),
        user_id: vid(),
        path: location.pathname + location.search,
        url: location.href,
        referrer: document.referrer || undefined,
        source: 'laravel_tracker',
        merchant_key: KEY,
        click_ids: Object.keys(now.clickIds).length ? now.clickIds : undefined,
        utm: Object.keys(now.utm).length ? now.utm : undefined,
        first_touch: first || undefined,
      },
      extra || {},
    );
    var json = JSON.stringify(body);
    var headers = {
      'Content-Type': 'application/json',
      'x-merchant-key': KEY,
    };
    if (DEPLOY) headers['x-fc-deploy-env'] = DEPLOY;
    try {
      fetch(API + '/api/v1/tracking/collect', {
        method: 'POST',
        headers: headers,
        body: json,
        keepalive: true,
        credentials: 'omit',
        mode: 'cors',
      }).catch(function () {});
    } catch (e) {}
    return true;
  }
  window.esbTrack = send;
  send('page_view');
})();
</script>
@endif
