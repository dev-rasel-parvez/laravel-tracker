{{-- EcomSolveBD Checkout Drop: full checkout form snapshot (v1.0.17+). Same code as tracker/src/checkout-form-snapshot.js.
     Sends what the shopper typed (address, district, thana, note, your own fields) once a phone/email is typed,
     so "Place order" on Checkout Drop opens the order form already filled in. Never reads passwords / card / OTP. --}}
@verbatim
<script>
(function () {
  if (typeof window.esbTrack !== 'function') return;
  /**
   * Checkout Drop "full form" capture — platform-agnostic (Woo classic / Blocks / CartFlows / FunnelKit /
   * CheckoutWC, Laravel, Next.js, Custom, ESB store).
   *
   * Whatever fields a store's checkout has (one "full address" box, or separate district / thana / area,
   * extra custom fields, order note …), the latest typed values are sent as ONE debounced
   * `checkout_form_snapshot` hit. The server keeps only the newest snapshot per visitor (own table, never a
   * tracking event, never sent to GA4 / Meta / TikTok) and uses it when the Checkout Drop lead is written, so the
   * merchant's "Place order" button can pre-fill the whole order form.
   *
   * Never captured: passwords, card / CVV numbers, OTP / verification codes, hidden inputs, tokens / nonces.
   * Sent only after the form has a usable phone or email (same rule as the Checkout Drop lead itself).
   *
   * Plain ES5-style JS on purpose: copied verbatim into the WooCommerce bridge, the Laravel Blade snippet and the
   * Next.js package. Keep copies in sync (search "esbFormSnapshot").
   */

  var SNAP_EVENT = "checkout_form_snapshot";
  var MAX_FIELDS = 40;
  var MAX_VALUE = 500;
  var DEBOUNCE_MS = 1200;

  var SKIP_TYPES = /^(password|hidden|file|submit|button|image|reset|search)$/i;
  var SECRET_RE =
    /(pass(word)?|pwd|card|cc[-_ ]?(num|number|exp|cvc|cvv)|cvv|cvc|security[-_ ]?code|otp|one[-_ ]?time|verification[-_ ]?code|token|nonce|csrf|captcha|_wpnonce|coupon|promo|voucher|search|newsletter|subscribe)/i;
  var NOISE_CHECKBOX_RE = /(terms|agree|policy|create[-_ ]?account|createaccount|newsletter|subscribe|remember|শর্ত)/i;

  function sAttr(el, n) {
    try {
      return el && el.getAttribute ? String(el.getAttribute(n) || "") : "";
    } catch (e) {
      return "";
    }
  }

  function sClip(v, n) {
    return String(v == null ? "" : v)
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, n);
  }

  function isField(el) {
    if (!el || !el.tagName) return false;
    var t = el.tagName.toLowerCase();
    return t === "input" || t === "select" || t === "textarea";
  }

  function isVisible(el) {
    try {
      if (el.tagName.toLowerCase() === "select") return true; // select2 / custom dropdowns hide the native select
      if (el.offsetParent !== null) return true;
      var cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
      return !!(cs && cs.position === "fixed" && cs.display !== "none" && cs.visibility !== "hidden");
    } catch (e) {
      return true;
    }
  }

  function fieldLabel(el) {
    try {
      if (el.labels && el.labels.length) {
        var lt = sClip(el.labels[0].innerText || el.labels[0].textContent, 80).replace(/\s*\*\s*$/, "");
        if (lt) return lt;
      }
      var aria = sAttr(el, "aria-label");
      if (aria) return sClip(aria, 80);
      if (el.placeholder) return sClip(el.placeholder, 80);
      var row = el.closest ? el.closest(".form-row, .wfacp-form-control-wrapper, .wcf-field, .field, .form-group, label") : null;
      if (row) {
        var lab = row.querySelector ? row.querySelector("label, .label, .field-label") : null;
        var txt = sClip((lab || row).innerText || (lab || row).textContent, 80);
        if (txt && txt.length <= 60) return txt.replace(/\s*\*\s*$/, "");
      }
    } catch (e) {
      /* ignore */
    }
    return "";
  }

  function fieldKey(el) {
    return sClip(el.name || el.id || sAttr(el, "data-name") || sAttr(el, "autocomplete"), 80);
  }

  function fieldValue(el) {
    var t = el.tagName.toLowerCase();
    if (t === "select") {
      var o = el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex] : null;
      if (!o || !String(o.value || "").trim()) return "";
      return sClip(o.text || o.value, MAX_VALUE);
    }
    var type = String(el.type || "").toLowerCase();
    if (type === "radio") {
      if (!el.checked) return "";
      return sClip(fieldLabel(el) || el.value, MAX_VALUE);
    }
    if (type === "checkbox") {
      if (!el.checked) return "";
      var l = fieldLabel(el);
      if (NOISE_CHECKBOX_RE.test(l + " " + el.name)) return "";
      return sClip(l || "Yes", MAX_VALUE);
    }
    return sClip(el.value, MAX_VALUE);
  }

  /** Canonical role from key + label — mirrors server/src/tracking/services/checkout-form-snapshot.service.ts. */
  function roleOf(key, label, type) {
    var hay = (key + " " + label).toLowerCase();
    if (type === "email" || /(^|[^a-z])(e-?mail)([^a-z]|$)|ইমেইল|ই-মেইল/.test(hay)) return "email";
    if (type === "tel" || /(phone|mobile|tel\b|whatsapp|contact[_ ]?number|মোবাইল|ফোন|নাম্বার|নম্বর)/.test(hay)) return "phone";
    if (/(first[_ -]?name|fname|given)/.test(hay)) return "firstName";
    if (/(last[_ -]?name|lname|surname|family)/.test(hay)) return "lastName";
    if (/(order[_ ]?comments|order[_ ]?note|note|notes|comment|message|instruction|নোট|মন্তব্য)/.test(hay)) return "note";
    if (/(thana|upazila|upozila|police[_ ]?station|থানা|উপজেলা)/.test(hay)) return "thana";
    if (/(district|zila|zilla|জেলা)/.test(hay)) return "district";
    if (/(postcode|post[_ ]?code|postal|zip)/.test(hay)) return "postcode";
    if (/(country|দেশ)/.test(hay)) return "country";
    if (/(address|street|house|road|ঠিকানা)/.test(hay) && !/(address[_ ]?2|apartment|suite)/.test(hay)) return "address";
    if (/(area|এলাকা|এরিয়া)/.test(hay)) return "area";
    if (/(^|[^a-z])state([^a-z]|$)|division|বিভাগ/.test(hay)) return "district";
    if (/(^|[^a-z])city([^a-z]|$)|শহর/.test(hay)) return "city";
    if (/(full[_ ]?name|your[_ ]?name|customer[_ ]?name|billing[_ ]?name|(^|[^a-z])name([^a-z]|$)|নাম)/.test(hay)) return "name";
    return "";
  }

  /** The form (or form-like container) around a field; null when it is not a checkout-ish form. */
  function scopeFor(el) {
    if (!el || !el.closest) return null;
    var form = el.closest("form");
    if (form) return form;
    // React / no-<form> checkouts: nearest ancestor with a few fields.
    var node = el.parentElement;
    for (var i = 0; node && i < 8; i += 1) {
      if (node.querySelectorAll && node.querySelectorAll("input, select, textarea").length >= 3) return node;
      node = node.parentElement;
    }
    return null;
  }

  /** Read every capturable field of a scope. */
  function readCheckoutFormSnapshot(scope) {
    var out = { fields: [], mapped: {} };
    if (!scope || !scope.querySelectorAll) return out;
    var els = scope.querySelectorAll("input, select, textarea");
    var seenRadio = {};
    for (var i = 0; i < els.length && out.fields.length < MAX_FIELDS; i += 1) {
      var el = els[i];
      var type = String(el.type || "").toLowerCase();
      if (SKIP_TYPES.test(type)) continue;
      if (el.disabled) continue;
      var key = fieldKey(el);
      var label = fieldLabel(el);
      if (SECRET_RE.test(key) || SECRET_RE.test(label) || SECRET_RE.test(sAttr(el, "autocomplete"))) continue;
      if (/^cc-/.test(sAttr(el, "autocomplete"))) continue;
      if (type !== "radio" && type !== "checkbox" && !isVisible(el)) continue;
      if (type === "radio") {
        if (seenRadio[el.name]) continue;
        if (!el.checked) continue;
        seenRadio[el.name] = 1;
        // Radio group label = the group's heading, not the option.
        var grp = el.closest ? el.closest("fieldset, .form-row, [role=radiogroup]") : null;
        var legend = grp && grp.querySelector ? grp.querySelector("legend, label:not([for])") : null;
        if (legend) label = sClip(legend.innerText || legend.textContent, 80) || label;
      }
      var v = fieldValue(el);
      if (!v) continue;
      // Never send anything that looks like a card number.
      if (v.replace(/\D/g, "").length >= 13 && /^[\d\s-]+$/.test(v)) continue;
      out.fields.push({ k: key, l: label, v: v });
      var role = roleOf(key, label, type);
      if (role && !out.mapped[role]) out.mapped[role] = v;
      else if (role === "address" && out.mapped.address && out.mapped.address.indexOf(v) < 0) {
        out.mapped.address = sClip(out.mapped.address + ", " + v, MAX_VALUE);
      }
    }
    if (!out.mapped.name && (out.mapped.firstName || out.mapped.lastName)) {
      out.mapped.name = sClip((out.mapped.firstName || "") + " " + (out.mapped.lastName || ""), 120);
    }
    return out;
  }

  /** ০-৯ → 0-9 so Bangla-digit phone numbers count. */
  function asciiDigits(v) {
    return String(v == null ? "" : v).replace(/[০-৯]/g, function (d) {
      return String("০১২৩৪৫৬৭৮৯".indexOf(d));
    });
  }

  function hasContact(m) {
    return (
      !!(m.phone && asciiDigits(m.phone).replace(/\D/g, "").length >= 8) ||
      !!(m.email && String(m.email).indexOf("@") > 0)
    );
  }

  /**
   * @param {(event: string, props?: Record<string, unknown>) => unknown} track
   */
  function installCheckoutFormSnapshot(track) {
    if (typeof document === "undefined" || typeof track !== "function") return;
    var root = document.documentElement;
    if (!root || root.getAttribute("data-esb-form-snapshot") === "1") return;
    root.setAttribute("data-esb-form-snapshot", "1");

    var timer = null;
    var pendingScope = null;
    var lastSent = "";

    function send() {
      timer = null;
      var scope = pendingScope;
      if (!scope) return;
      try {
        var snap = readCheckoutFormSnapshot(scope);
        if (!snap.fields.length || !hasContact(snap.mapped)) return;
        // Login / sign-up boxes (email + password, no phone or address) are not checkouts.
        if (scope.querySelector && scope.querySelector("input[type=password]") && !snap.mapped.phone && !snap.mapped.address) return;
        var sig = JSON.stringify(snap.fields);
        if (sig === lastSent) return;
        lastSent = sig;
        track(SNAP_EVENT, { checkout_fields: { fields: snap.fields, mapped: snap.mapped } });
      } catch (e) {
        /* never break the store's checkout */
      }
    }

    function onField(ev) {
      try {
        var el = ev && ev.target;
        if (!isField(el)) return;
        var scope = scopeFor(el);
        if (!scope) return;
        pendingScope = scope;
        if (timer) clearTimeout(timer);
        timer = setTimeout(send, DEBOUNCE_MS);
      } catch (e) {
        /* ignore */
      }
    }

    function flush() {
      if (!timer) return;
      clearTimeout(timer);
      send();
    }

    document.addEventListener("input", onField, true);
    document.addEventListener("change", onField, true);
    document.addEventListener("blur", onField, true);
    // Leaving / switching app before the debounce fires — send what we have.
    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "hidden") flush();
    });
    window.addEventListener("pagehide", flush);
    // Submit: send the final state before the page moves on.
    document.addEventListener(
      "submit",
      function (ev) {
        try {
          var f = ev && ev.target;
          if (f && f.querySelectorAll) {
            pendingScope = f;
            if (timer) clearTimeout(timer);
            send();
          }
        } catch (e) {
          /* ignore */
        }
      },
      true,
    );
  }

  try {
    installCheckoutFormSnapshot(function (ev, props) {
      window.esbTrack(ev, props || {});
    });
  } catch (e) {}
})();
</script>
@endverbatim
