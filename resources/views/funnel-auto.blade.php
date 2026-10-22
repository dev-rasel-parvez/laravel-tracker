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

  var ESB_EMAIL_TOKEN =
    /(^|[^a-z0-9])(e-?mail|user_email|customer_email|billing_email|shipping_email|contact_email|checkout_email|email_address|mailaddress|ইমেইল|ই-মেইল)([^a-z0-9]|$)/i;
  var ESB_PHONE_TOKEN =
    /(^|[^a-z0-9])(phone|mobile|cellphone|whatsapp|billing_phone|shipping_phone|contact_phone|customer_phone|checkout_phone|phone_number|phonenumber|tel|telephone|মোবাইল|ফোন)([^a-z0-9]|$)/i;

  function esbFieldAttr(el, name) {
    try {
      return el && el.getAttribute ? String(el.getAttribute(name) || '') : '';
    } catch (e) {
      return '';
    }
  }

  function classifyCheckoutContactField(el) {
    if (!el || el.disabled) return null;
    var tag = String(el.tagName || '').toLowerCase();
    if (tag !== 'input' && tag !== 'textarea') return null;
    var type = String(el.type || 'text').toLowerCase();
    if (
      type === 'hidden' ||
      type === 'checkbox' ||
      type === 'radio' ||
      type === 'submit' ||
      type === 'button' ||
      type === 'password' ||
      type === 'file' ||
      type === 'image'
    ) {
      return null;
    }
    if (type === 'email') return 'email';
    if (type === 'tel') return 'phone';
    var ac = String(el.autocomplete || esbFieldAttr(el, 'autocomplete') || '')
      .toLowerCase()
      .split(' ')
      .pop();
    if (ac === 'email') return 'email';
    if (ac === 'tel' || ac === 'tel-national' || ac === 'tel-local' || ac === 'tel-country-code' || ac === 'mobile') {
      return 'phone';
    }
    var mode = String(el.inputMode || esbFieldAttr(el, 'inputmode') || '').toLowerCase();
    if (mode === 'email') return 'email';
    if (mode === 'tel') return 'phone';
    var hay = [
      el.name,
      el.id,
      el.className,
      el.placeholder,
      el.type,
      el.inputMode,
      esbFieldAttr(el, 'autocomplete'),
      esbFieldAttr(el, 'inputmode'),
      esbFieldAttr(el, 'aria-label'),
      esbFieldAttr(el, 'data-field'),
      esbFieldAttr(el, 'data-name'),
      esbFieldAttr(el, 'data-testid'),
      esbFieldAttr(el, 'data-esb-track'),
    ]
      .join(' ')
      .toLowerCase();
    if (ESB_EMAIL_TOKEN.test(hay)) return 'email';
    if (ESB_PHONE_TOKEN.test(hay)) return 'phone';
    return null;
  }

  function markContactValueFired(kind, value) {
    window.__esbContactValueFired = window.__esbContactValueFired || {};
    var raw = String(value || '').trim();
    if (!raw || (kind !== 'email' && kind !== 'phone')) return false;
    var norm = kind === 'phone' ? raw.replace(/\D/g, '').slice(-10) : raw.toLowerCase();
    if (kind === 'phone' && norm.length < 8) return false;
    if (kind === 'email' && norm.indexOf('@') < 1) return false;
    var key = kind + ':' + norm;
    if (window.__esbContactValueFired[key]) return false;
    window.__esbContactValueFired[key] = 1;
    return true;
  }

  function emitContactIfNeeded(el, eventNameHint) {
    var v = String(el && el.value ? el.value : '').trim();
    if (!v) return false;
    var kind = classifyCheckoutContactField(el);
    var eventName = eventNameHint || '';
    if (kind === 'email') eventName = 'email_address_added';
    else if (kind === 'phone') eventName = 'phone_number_added';
    if (eventName !== 'email_address_added' && eventName !== 'phone_number_added') return false;
    var contactKind = eventName === 'email_address_added' ? 'email' : 'phone';
    if (!markContactValueFired(contactKind, v)) return true;
    var props = { field_value_len: v.length };
    if (contactKind === 'phone') props.phone = v;
    if (contactKind === 'email') props.email = v;
    window.esbTrack(eventName, props);
    return true;
  }

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
          if (!el) return;
          if (emitContactIfNeeded(el)) return;
          if (!el.name) return;
          var key = String(el.name).toLowerCase().replace(/\[\]$/, '');
          var short = key.split(/[\[\].]/).filter(Boolean).pop() || key;
          var eventName = map[short] || map[key];
          if (!eventName) return;
          var v = String(el.value || '').trim();
          if (!v) return;
          var props = { field_value_len: v.length };
          if (eventName === 'phone_number_added' || eventName === 'email_address_added') {
            emitContactIfNeeded(el, eventName);
            return;
          }
          window.esbTrack(eventName, props);
        },
        true,
      );
      form.addEventListener('submit', function () {
        window.esbTrack('checkout_form_completed', {});
        window.__esbCheckoutCompleted = true;
      });
      return true;
    } catch (e) {
      return false;
    }
  };

  // Contact fields + data-esb-track=\"event_name\" — type/autocomplete/name/placeholder, not only name=email|phone
  document.addEventListener(
    'change',
    function (ev) {
      try {
        var el = ev.target;
        if (!el || !el.getAttribute) return;
        var evName = el.getAttribute('data-esb-track');
        if (emitContactIfNeeded(el, evName || '')) return;
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
