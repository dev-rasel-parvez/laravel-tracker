{{-- EcomSolveBD browser collect (Woo-parity: 6-char uid, utm, click_ids, first_touch, GA cookies). --}}
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
  var SESS_KEY = 'esb_sid';

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

  /** Woo parity: 5–8 char [a-z0-9]. */
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

  function sessionId() {
    try {
      var s = localStorage.getItem(SESS_KEY);
      if (s && /^\d{9,16}$/.test(s)) return s;
      s = String(Math.floor(Date.now() / 1000));
      localStorage.setItem(SESS_KEY, s);
      return s;
    } catch (e) {
      return String(Math.floor(Date.now() / 1000));
    }
  }

  function cookieVal(name) {
    try {
      var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
      if (!m || !m[1]) return '';
      try {
        return decodeURIComponent(m[1]);
      } catch (e) {
        return m[1];
      }
    } catch (e2) {
      return '';
    }
  }

  /** Woo `tracker/src/ga-cookies.js` — _ga → client_id */
  function readGaClientId() {
    var raw = cookieVal('_ga');
    if (!raw || raw.indexOf('GA1.') !== 0) return null;
    var parts = raw.split('.');
    if (parts.length < 4) return null;
    var cid = String(parts[2]) + '.' + String(parts[3]);
    return cid && cid !== '.' ? cid.slice(0, 128) : null;
  }

  function readGa4SessionCookieRaw() {
    try {
      var parts = String(document.cookie || '').split(';');
      for (var i = 0; i < parts.length; i++) {
        var seg = parts[i];
        if (!seg) continue;
        var eq = seg.indexOf('=');
        if (eq < 0) continue;
        var name = seg.slice(0, eq).replace(/^\s+|\s+$/g, '');
        if (name.indexOf('_ga_') === 0 && name.length > 4) {
          var val = seg.slice(eq + 1);
          try {
            val = decodeURIComponent(val);
          } catch (e) {}
          if (val) return val.trim();
        }
      }
    } catch (e2) {}
    return null;
  }

  /** Parse `_ga_<MID>` → ga_session_id / ga_session_number (Woo parity). */
  function parseGa4Session(raw) {
    var out = { ga_session_id: null, ga_session_number: null };
    if (!raw || typeof raw !== 'string') return out;
    var v = raw.trim();
    var dotMatch = v.match(/^GS\d+\.\d+\.(\d+)\.(\d+)/);
    if (dotMatch) {
      out.ga_session_id = String(dotMatch[1]).slice(0, 32);
      out.ga_session_number = String(dotMatch[2]).slice(0, 16);
      return out;
    }
    var sPrefix = v.match(/^GS\d+\.\d+\.s(\d+)/i);
    if (sPrefix) {
      out.ga_session_id = String(sPrefix[1]).slice(0, 32);
      var oAfterS = v.match(/\$o(\d+)\$/i);
      if (oAfterS && oAfterS[1]) out.ga_session_number = String(oAfterS[1]).slice(0, 16);
      return out;
    }
    var prefixMatch = v.match(/^GS\d+\.\d+\./);
    if (!prefixMatch) return out;
    var rest = v.slice(prefixMatch[0].length);
    var dollar = rest.indexOf('$');
    if (dollar > 0) {
      var sid = rest.slice(0, dollar);
      if (/^\d+$/.test(sid)) out.ga_session_id = sid.slice(0, 32);
    }
    var oMatch = v.match(/\$o(\d+)\$/i);
    if (oMatch && oMatch[1]) out.ga_session_number = String(oMatch[1]).slice(0, 16);
    return out;
  }

  function gaMetrics() {
    var session = parseGa4Session(readGa4SessionCookieRaw() || '');
    var clientId = readGaClientId();
    var out = {};
    if (clientId) out.client_id = clientId;
    if (session.ga_session_id) out.ga_session_id = session.ga_session_id;
    if (session.ga_session_number) out.ga_session_number = session.ga_session_number;
    return out;
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
    var sid = sessionId();
    var ga = gaMetrics();
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
        session_id: sid,
        sass_session_id: sid,
        sass_session_number: 1,
        click_ids: Object.keys(now.clickIds).length ? now.clickIds : undefined,
        utm: Object.keys(now.utm).length ? now.utm : undefined,
        first_touch: first || undefined,
      },
      ga,
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
  // gtag often writes `_ga` / `_ga_*` after first paint — re-hit so purchase identity gets session_id_from=ga
  setTimeout(function () {
    try {
      var ga = gaMetrics();
      if (ga.client_id || ga.ga_session_id) send('page_view');
    } catch (e) {}
  }, 2000);
})();
</script>
@endif
