<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Rules;

use EcomSolveBD\LaravelTracker\CheckoutShield;
use Illuminate\Contracts\Validation\Rule;

/**
 * Validation rule for the checkout phone field:
 *
 *   'phone' => ['required', new CheckoutShieldPhone()],
 *
 * Fails only when the store's Checkout Shield says this phone must verify its SMS code first, or is under the
 * repeat-order timer. Our API being slow or down never fails the rule.
 */
final class CheckoutShieldPhone implements Rule
{
    private string $message = 'অর্ডার নিশ্চিত করতে মোবাইলে পাঠানো কোড দিন।';

    public function passes($attribute, $value): bool
    {
        $r = app(CheckoutShield::class)->verify(is_scalar($value) ? (string) $value : '');
        if (! $r['allow']) {
            $this->message = $r['message'];
        }
        return $r['allow'];
    }

    public function message(): string
    {
        return $this->message;
    }
}
