<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class AllowedVendorPseudonym implements ValidationRule
{
    private const RESERVED = [
        'admin', 'administrator', 'support', 'help', 'security', 'moderator', 'moderator', 'koshermarket',
        'kosher-market', 'kosher_market', 'official', 'staff', 'system', 'root', 'owner', 'bitcoin', 'btc',
        'marketplace', 'vendor', 'seller', 'customer', 'api', 'billing', 'payments', 'payment', 'finance',
        'escrow', 'noreply', 'no-reply', 'contact', 'info', 'sales', 'service', 'superadmin',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = Str::of((string) $value)->lower()->replaceMatches('/[^a-z0-9]/', '')->toString();

        if (in_array($normalized, self::RESERVED, true)) {
            $fail('This pseudonym is reserved and cannot be used.');

            return;
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{2,29}$/', (string) $value)) {
            $fail('Use 3–30 characters: letters, numbers, dots, underscores or hyphens.');
        }
    }
}
