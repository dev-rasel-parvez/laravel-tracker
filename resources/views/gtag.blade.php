{{-- GA4 base install (auto-events from gtag). Ecommerce / custom events come from EcomSolveBD SaaS. --}}
@php
  $mid = trim((string) config('ecomsolvebd.ga4_measurement_id', ''));
@endphp
@if($mid !== '')
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $mid }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', @json($mid));
</script>
@endif
