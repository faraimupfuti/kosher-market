<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BitcoinAmount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value) || (float) $value < 0) {
            $fail('The :attribute must be a valid non-negative Bitcoin amount.');
        }
    }
}
