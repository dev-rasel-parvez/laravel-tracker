{{-- Optional funnel auto-bind (v1.0.8+). Manual window.esbTrack always wins / can override. --}}
@php
  $funnel = config('ecomsolvebd.funnel', []);
  $autoOn = filter_var($funnel['auto'] ?? true, FILTER_VALIDATE_BOOLEAN);
  $productSel = (string) ($funnel['product_selector'] ?? '[data-esb-product],[data-product-id],.product-card,[itemtype*="Product"]');
  $addSel = (string) ($funnel['add_to_cart_selector'] ?? '[data-esb-add-to-cart],button.add-to-cart,.add-to-cart,[name=\"add-to-cart\"]');
  $removeSel = (string) ($funnel['remove_from_cart_selector'] ?? '[data-esb-remove-from-cart],.remove-from-cart,.cart-remove');
  $cartPath = (string) ($funnel['cart_path_contains'] ?? '/cart');
  $checkoutPath = (string) ($funnel['checkout_path_contains'] ?? '/checkout');
@endphp
@if($autoOn)
<script>
(function () {
  if (typeof window.esbTrack !== 'function') return;
  var AUTO = true;
  var PRODUCT_SEL = @json($productSel);
  var ADD_SEL = @json($addSel);
  var REMOVE_SEL = @json($removeSel);
  var CART_NEEDLE = @json($cartPath);
  var CHECKOUT_NEEDLE = @json($checkoutPath);
  var fired = Object.create(null);
  var onceKey = function (k) {
    if (fired[k]) return false;
    fired[k] = 1;
    return true;
  };

  function readProductFromEl(el) {
    if (!el || !el.getAttribute) return null;
    var id =
      el.getAttribute('data-esb-product') ||
      el.getAttribute('data-product-id') ||
      el.getAttribute('data-id') ||
      '';
    var name = el.getAttribute('data-esb-name') || el.getAttribute('data-product-name') || '';
    var price = parseFloat(el.getAttribute('data-esb-price') || el.getAttribute('data-price') || '0') || 0;
    if (!id && !name) {
      var title = el.querySelector && el.querySelector('h1,h2,.product-title,[itemprop=\"name\"]');
      if (title) name = (title.textContent || '').trim();
    }
    if (!id && !name) return null;
    return {
      item_id: String(id || name).slice(0, 64),
      item_name: String(name || id).slice(0, 200),
      price: price,
      quantity: 1,
    };
  }

  function trackEcommerce(eventName, value, items) {
    try {
      window.esbTrack(eventName, {
        ecommerce: {
          currency: 'BDT',
          value: Number(value) || 0,
          items: items || [],
        },
      });
    } catch (e) {}
  }

  // view_item — PDP / product root
  try {
    var path = String(location.pathname || '');
    var isPdp = /\/product\//i.test(path) || document.body.classList.contains('product') || document.querySelector('[data-esb-view-item]');
    if (isPdp && onceKey('view_item')) {
      var root =
        document.querySelector('[data-esb-view-item]') ||
        document.querySelector(PRODUCT_SEL) ||
        document.querySelector('main') ||
        document.body;
      var item = readProductFromEl(root);
      if (item) trackEcommerce('view_item', item.price, [item]);
      else window.esbTrack('view_item', {});
    }
  } catch (e) {}

  // view_cart / begin_checkout — path heuristics
  try {
    var p = String(location.pathname || '').toLowerCase();
    if (CART_NEEDLE && p.indexOf(String(CART_NEEDLE).toLowerCase()) !== -1 && onceKey('view_cart')) {
      window.esbTrack('view_cart', {});
    }
    if (CHECKOUT_NEEDLE && p.indexOf(String(CHECKOUT_NEEDLE).toLowerCase()) !== -1 && onceKey('begin_checkout')) {
      window.esbTrack('begin_checkout', {});
    }
  } catch (e2) {}

  // add_to_cart / remove_from_cart — click delegation
  document.addEventListener(
    'click',
    function (ev) {
      try {
        var t = ev.target;
        if (!t || !t.closest) return;
        var addBtn = t.closest(ADD_SEL);
        if (addBtn) {
          var card = addBtn.closest(PRODUCT_SEL) || addBtn;
          var it = readProductFromEl(card) || { item_id: 'item', item_name: 'Item', price: 0, quantity: 1 };
          trackEcommerce('add_to_cart', it.price * (it.quantity || 1), [it]);
          return;
        }
        var rm = t.closest(REMOVE_SEL);
        if (rm) {
          var card2 = rm.closest(PRODUCT_SEL) || rm;
          var it2 = readProductFromEl(card2) || { item_id: 'item', item_name: 'Item', price: 0, quantity: 1 };
          trackEcommerce('remove_from_cart', it2.price * (it2.quantity || 1), [it2]);
        }
      } catch (e3) {}
    },
    true,
  );

  /**
   * Helper: bind a checkout form for form/field events (manual opt-in).
   * Example: window.esbBindCheckoutForm('#checkout-form')
   */
  window.esbBindCheckoutForm = function (formSelector, fieldMap) {
    try {
      var form = typeof formSelector === 'string' ? document.querySelector(formSelector) : formSelector;
      if (!form) return false;
      var map = fieldMap || {
        first_name: 'first_name_added',
        last_name: 'last_name_added',
        phone: 'phone_number_added',
        email: 'email_address_added',
        address: 'address_added',
        city: 'city_added',
        district: 'city_added',
        state: 'state_added',
        postal_code: 'postal_code_added',
        zip: 'postal_code_added',
        country: 'country_added',
      };
      var started = false;
      var markStarted = function () {
        if (started) return;
        started = true;
        window.esbTrack('checkout_form_started', {});
      };
      form.addEventListener('focusin', markStarted, true);
      form.addEventListener(
        'change',
        function (ev) {
          markStarted();
          var el = ev.target;
          if (!el || !el.name) return;
          var key = String(el.name).toLowerCase().replace(/\[\]$/, '');
          var short = key.split(/[\[\].]/).filter(Boolean).pop() || key;
          var eventName = map[short] || map[key];
          if (!eventName) return;
          var v = String(el.value || '').trim();
          if (!v) return;
          window.esbTrack(eventName, { field_value_len: v.length });
        },
        true,
      );
      form.addEventListener('submit', function () {
        window.esbTrack('checkout_form_completed', {});
        window.__esbCheckoutCompleted = true;
      });
      window.addEventListener('pagehide', function () {
        if (started && !window.__esbCheckoutCompleted) {
          window.esbTrack('checkout_form_abandoned', {});
        }
      });
      return true;
    } catch (e) {
      return false;
    }
  };

  // data-esb-track=\"event_name\" soft bind
  document.addEventListener(
    'change',
    function (ev) {
      try {
        var el = ev.target;
        if (!el || !el.getAttribute) return;
        var evName = el.getAttribute('data-esb-track');
        if (!evName) return;
        var v = String(el.value || '').trim();
        if (!v) return;
        window.esbTrack(evName, { field_value_len: v.length });
      } catch (e) {}
    },
    true,
  );

  if (AUTO) {
    /* auto-bind active */
  }
})();
</script>
@endif
