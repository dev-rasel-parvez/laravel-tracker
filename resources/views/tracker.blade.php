{{-- EcomSolveBD browser collect snippet (MVP). Include via @ecomsolvebdTracker in layout. --}}
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
  function vid() {
    try {
      var k = 'esb_vid';
      var v = localStorage.getItem(k);
      if (v && /^[a-z0-9]{4,32}$/i.test(v)) return v;
      v = (Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)).slice(0, 16);
      localStorage.setItem(k, v);
      document.cookie = 'esb_vid=' + encodeURIComponent(v) + ';path=/;max-age=31536000;SameSite=Lax';
      return v;
    } catch (e) {
      return 'anon' + String(Date.now()).slice(-8);
    }
  }
  function send(eventName, extra) {
    var body = Object.assign({
      event: eventName,
      timestamp: new Date().toISOString(),
      user_id: vid(),
      path: location.pathname,
      url: location.href,
      referrer: document.referrer || undefined,
      source: 'laravel_tracker',
      merchant_key: KEY,
    }, extra || {});
    var json = JSON.stringify(body);
    var headers = {
      'Content-Type': 'application/json',
      'x-merchant-key': KEY,
    };
    if (DEPLOY) headers['x-fc-deploy-env'] = DEPLOY;
    try {
      // Prefer fetch so DevTools Network → Fetch/XHR shows the request (sendBeacon often hides there).
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
