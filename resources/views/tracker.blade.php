{{-- EcomSolveBD browser collect snippet (MVP). Include via @ecomsolvebdTracker in layout. --}}
@php
  $apiBase = rtrim((string) config('ecomsolvebd.api_base', 'https://api.ecomsolvebd.com'), '/');
  $merchantKey = (string) config('ecomsolvebd.merchant_key', '');
@endphp
@if($merchantKey !== '')
<script>
(function () {
  var API = @json($apiBase);
  var KEY = @json($merchantKey);
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
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(API + '/api/v1/tracking/collect', new Blob([json], { type: 'application/json' }));
      } else {
        fetch(API + '/api/v1/tracking/collect', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'x-merchant-key': KEY },
          body: json,
          keepalive: true,
          credentials: 'omit',
        });
      }
    } catch (e) {}
  }
  window.esbTrack = send;
  send('page_view');
})();
</script>
@endif
