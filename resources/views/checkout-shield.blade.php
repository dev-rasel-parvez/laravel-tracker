{{-- EcomSolveBD Checkout Shield: phone check on the 11th digit + Bangla SMS-code dialog on "Place order".
     Put @ecomsolvebdCheckoutShield on the checkout page only. Inert unless turned on for this store. --}}
@php
  $merchantKey = (string) config('ecomsolvebd.merchant_key', '');
  $apiBase = rtrim((string) config('ecomsolvebd.api_base', 'https://api.ecomsolvebd.com'), '/');
@endphp
@if($merchantKey !== '')
<script src="{{ $apiBase }}/api/v1/checkout-shield/public/script.js" data-merchant-key="{{ $merchantKey }}" data-api="{{ $apiBase }}" defer></script>
@endif
